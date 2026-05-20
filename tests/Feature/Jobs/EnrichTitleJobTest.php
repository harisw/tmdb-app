<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EnrichTitleJob;
use App\Models\Country;
use App\Models\Title;
use App\Services\TmdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class EnrichTitleJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_propagates_exceptions_so_the_job_fails_and_can_be_retried(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 123]);

        $this->mock(TmdbService::class)
            ->shouldReceive('getKeywords')
            ->andThrow(new RuntimeException('TMDB unavailable'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TMDB unavailable');

        (new EnrichTitleJob($title))->handle();
    }

    public function test_completes_successfully_and_does_not_throw_on_empty_results(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 456]);
        Country::factory()->create(['iso2' => 'US']);

        $tmdb = $this->mock(TmdbService::class);
        $tmdb->shouldReceive('getKeywords')
            ->andReturn(['keywords' => []]);
        $tmdb->shouldReceive('getWatchProviders')
            ->andReturn(['results' => []]);

        (new EnrichTitleJob($title))->handle();

        $this->assertDatabaseCount('title_providers', 0);
    }
}
