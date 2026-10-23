<?php

namespace Tests\Feature\Migrations;

use App\Models\Title;
use App\Models\TitleInteractionEvent;
use App\Models\UserTitleState;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RemoveAdultContentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_adult_titles_with_their_user_data_and_drops_the_adult_columns(): void
    {
        Schema::table('titles', fn (Blueprint $table) => $table->boolean('is_adult')->default(false));
        Schema::table('users', fn (Blueprint $table) => $table->boolean('hide_adult')->default(true));
        $adult = Title::factory()->create();
        $safe = Title::factory()->create();
        DB::table('titles')->where('id', $adult->id)->update(['is_adult' => true]);
        UserTitleState::factory()->for($adult)->create();
        TitleInteractionEvent::factory()->for($adult)->create();
        UserTitleState::factory()->for($safe)->create();

        (require database_path('migrations/2026_10_23_074112_remove_adult_content.php'))->up();

        $this->assertSame([$safe->id], Title::pluck('id')->all());
        $this->assertSame([$safe->id], UserTitleState::pluck('title_id')->all());
        $this->assertSame(0, TitleInteractionEvent::count());
        $this->assertFalse(Schema::hasColumn('titles', 'is_adult'));
        $this->assertFalse(Schema::hasColumn('users', 'hide_adult'));
    }
}
