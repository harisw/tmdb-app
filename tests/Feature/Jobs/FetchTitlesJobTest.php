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
use Tests\TestCase;

class FetchTitlesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_skips_a_feed_title_whose_details_are_gone_and_keeps_going(): void
    {
        Bus::fake();
        Language::factory()->create(['code' => 'en']);

        $this->mock(TmdbApiService::class, function ($mock) {
            $mock->shouldReceive('getPopulars')->andReturn([
                'results' => [['id' => 404], ['id' => 550]],
                'total_pages' => 1,
            ]);
            $mock->shouldReceive('getTitle')->with(TitleType::MOVIE, 404)->andReturn(null);
            $mock->shouldReceive('getTitle')->with(TitleType::MOVIE, 550)->andReturn([
                'id' => 550,
                'title' => 'Fight Club',
                'vote_average' => 8.4,
                'status' => 'Released',
                'release_date' => '1999-10-15',
                'adult' => false,
                'overview' => 'An insomniac office worker forms an underground fight club.',
                'popularity' => 61.4,
                'original_language' => 'en',
                'genres' => [],
                'production_companies' => [],
            ]);
        });

        (new FetchPopularTitlesJob(TitleType::MOVIE, limit: 2, maxPages: 1))
            ->handle(app(TmdbApiService::class), new TitleService);

        $this->assertSame([550], Title::withoutGlobalScopes()->pluck('tmdb_id')->all());
    }
}
