<?php

namespace Tests\Feature\Jobs;

use App\Domain\Enums\TitleType;
use App\Jobs\FetchPopularTitlesJob;
use App\Models\Language;
use App\Models\Title;
use App\Services\TitleService;
use App\Services\TmdbApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

class FetchTitlesJobStoresTitleWithEmptyReleaseDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unreleased_title_with_empty_release_date_is_stored_with_null_date(): void
    {
        $this->withoutExceptionHandling();
        Exceptions::fake();
        Bus::fake();

        Language::factory()->create(['code' => 'other']);
        $language = Language::factory()->create(['code' => 'hi']);

        $detail = [
            'id' => 1383500,
            'title' => 'Upcoming Epic',
            'vote_average' => 0,
            'status' => 'In Production',
            'release_date' => '',
            'revenue' => 0,
            'runtime' => 0,
            'adult' => false,
            'backdrop_path' => null,
            'imdb_id' => 'tt0000001',
            'overview' => 'Plot TBA.',
            'popularity' => 9.9,
            'poster_path' => '/poster.jpg',
            'tagline' => '',
            'original_language' => 'hi',
            'homepage' => '',
            'genres' => [],
            'production_companies' => [],
            'belongs_to_collection' => null,
        ];

        $this->mock(TmdbApiService::class, function ($mock) use ($detail) {
            $mock->shouldReceive('getPopulars')->andReturn([
                'results' => [['id' => 1383500]],
                'total_pages' => 1,
            ]);
            $mock->shouldReceive('getTitle')->andReturn($detail);
        });

        (new FetchPopularTitlesJob(TitleType::MOVIE, limit: 1, maxPages: 1))
            ->handle(app(TmdbApiService::class), new TitleService);

        // insertRow swallows failures via report(); surface the real exception.
        foreach (Exceptions::reported() as $reported) {
            throw $reported;
        }

        $title = Title::withoutGlobalScopes()->where('tmdb_id', 1383500)->first();
        $this->assertNotNull($title);
        $this->assertSame($language->id, $title->language_id);
        $this->assertNull($title->getRawOriginal('release_date'));
    }
}
