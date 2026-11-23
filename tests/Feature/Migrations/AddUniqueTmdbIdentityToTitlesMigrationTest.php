<?php

namespace Tests\Feature\Migrations;

use App\Models\Genre;
use App\Models\Title;
use App\Models\TitleInteractionEvent;
use App\Models\User;
use App\Models\UserTitleState;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AddUniqueTmdbIdentityToTitlesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require database_path('migrations/2026_11_23_040805_add_unique_tmdb_identity_to_titles_table.php');
        $this->migration->down();
    }

    public function test_merges_duplicate_titles_into_the_newest_and_moves_their_references(): void
    {
        [$thirdCopy, $duplicate, $keeper] = Title::factory()->count(3)->create(['tmdb_id' => 1, 'type' => 'movie']);
        $sameIdOtherType = Title::factory()->create(['tmdb_id' => 1, 'type' => 'tv']);
        $other = Title::factory()->create();

        [$drama, $horror] = Genre::factory()->count(2)->create();
        $keeper->genres()->attach($drama);
        $duplicate->genres()->attach([$drama->id, $horror->id]);
        $thirdCopy->genres()->attach($horror);

        $sharedUser = User::factory()->create();
        UserTitleState::factory()->for($keeper)->for($sharedUser)->create(['rate' => 5]);
        UserTitleState::factory()->for($duplicate)->for($sharedUser)->create(['rate' => 3]);
        $thirdCopyState = UserTitleState::factory()->for($thirdCopy)->create();
        $event = TitleInteractionEvent::factory()->for($duplicate)->create();

        DB::table('title_similarities')->insert([
            ['title_id' => $other->id, 'similar_title_id' => $duplicate->id, 'score' => 0.9],
            ['title_id' => $duplicate->id, 'similar_title_id' => $keeper->id, 'score' => 0.8],
        ]);

        $this->migration->up();

        $this->assertSame([$keeper->id, $sameIdOtherType->id, $other->id], Title::orderBy('id')->pluck('id')->all());
        $this->assertGreaterThan($duplicate->id, $keeper->id);
        $this->assertEqualsCanonicalizing([$drama->id, $horror->id], $keeper->genres()->pluck('genres.id')->all());
        $this->assertEquals(5, UserTitleState::where('user_id', $sharedUser->id)->sole()->rate);
        $this->assertSame($keeper->id, UserTitleState::where('user_id', $thirdCopyState->user_id)->sole()->title_id);
        $this->assertSame($keeper->id, $event->fresh()->title_id);
        $this->assertSame(
            [[$other->id, $keeper->id]],
            DB::table('title_similarities')->get()->map(fn (object $row): array => [$row->title_id, $row->similar_title_id])->all(),
        );

        $this->expectException(UniqueConstraintViolationException::class);
        Title::factory()->create(['tmdb_id' => 1, 'type' => 'movie']);
    }

    public function test_replaces_empty_slugs_with_type_and_tmdb_id(): void
    {
        $title = Title::factory()->create(['slug' => '', 'tmdb_id' => 1720717, 'type' => 'movie']);

        $this->migration->up();

        $this->assertSame('movie-1720717', $title->fresh()->slug);
    }
}
