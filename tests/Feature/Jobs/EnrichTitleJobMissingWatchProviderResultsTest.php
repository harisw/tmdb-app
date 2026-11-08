<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EnrichTitleJob;
use App\Models\Title;
use App\Services\TmdbApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrichTitleJobMissingWatchProviderResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_completes_when_watch_providers_response_has_no_results_key(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 789, 'enriched_at' => null]);

        $tmdb = $this->mock(TmdbApiService::class);
        $tmdb->shouldReceive('getKeywords')
            ->andReturn(['keywords' => []]);
        $tmdb->shouldReceive('getWatchProviders')
            ->andReturn(['id' => 789]);

        (new EnrichTitleJob($title))->handle();

        $this->assertDatabaseCount('title_providers', 0);
        $this->assertNotNull($title->fresh()->enriched_at);
    }
}
