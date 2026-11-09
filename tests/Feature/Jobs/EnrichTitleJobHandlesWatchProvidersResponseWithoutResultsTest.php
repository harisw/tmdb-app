<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EnrichTitleJob;
use App\Models\Country;
use App\Models\Title;
use App\Services\TmdbApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrichTitleJobHandlesWatchProvidersResponseWithoutResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_completes_when_watch_providers_response_has_no_results_key(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 789, 'enriched_at' => null]);
        Country::factory()->create(['iso2' => 'US']);

        $tmdb = $this->mock(TmdbApiService::class);
        $tmdb->shouldReceive('getKeywords')
            ->andReturn(['keywords' => []]);
        $tmdb->shouldReceive('getWatchProviders')
            ->andReturn(['success' => false, 'status_code' => 34]);

        (new EnrichTitleJob($title))->handle();

        $this->assertNotNull($title->fresh()->enriched_at);
        $this->assertDatabaseCount('title_providers', 0);
    }
}
