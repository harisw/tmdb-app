<?php

namespace Tests\Feature\Service;

use App\Data\DTOs\TmdbApi\TmdbTitleData;
use App\Domain\Enums\TitleType;
use App\Models\Genre;
use App\Models\Language;
use App\Models\Title;
use App\Services\TitleService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

class TitleServiceConcurrentGenreAttachTest extends TestCase
{
    use RefreshDatabase;

    public function test_insert_row_tolerates_genre_attached_by_concurrent_worker(): void
    {
        // Surface anything the service reports instead of silently swallowing it.
        Exceptions::fake()->throwOnReport();

        $language = Language::factory()->create();
        $genre = Genre::factory()->create(['name' => 'Drama', 'slug' => 'drama']);

        $row = TmdbTitleData::from([
            'id' => 4242,
            'title' => 'Race Condition',
            'type' => TitleType::MOVIE,
            'vote_average' => 7.1,
            'status' => 'Released',
            'release_date' => '2020-01-01',
            'adult' => false,
            'overview' => 'Two workers, one title.',
            'popularity' => 10.0,
            'language_id' => $language->id,
            'genres' => [['name' => 'Drama']],
            'production_companies' => [],
        ]);

        $service = new TitleService;

        // Simulate another Horizon worker attaching the same genre right after
        // this worker read the currently attached pivots for the title.
        $injected = false;
        DB::listen(function (QueryExecuted $query) use (&$injected, $genre) {
            if ($injected) {
                return;
            }
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, '"genre_title"')) {
                $injected = true;
                DB::table('genre_title')->insert([
                    'genre_id' => $genre->id,
                    'title_id' => $query->bindings[0],
                ]);
            }
        });

        $title = $service->insertRow($row);

        $this->assertTrue($injected);
        $this->assertInstanceOf(Title::class, $title);
        $this->assertSame(1, DB::table('genre_title')
            ->where('title_id', $title->id)
            ->where('genre_id', $genre->id)
            ->count());
    }
}
