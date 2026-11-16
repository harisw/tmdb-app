<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EnrichTitleJob;
use App\Models\Country;
use App\Models\Keyword;
use App\Models\Title;
use App\Services\TmdbApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class EnrichTitleJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_propagates_exceptions_so_the_job_fails_and_can_be_retried(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 123]);

        $this->mock(TmdbApiService::class)
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

        $tmdb = $this->mock(TmdbApiService::class);
        $tmdb->shouldReceive('getKeywords')
            ->andReturn(['keywords' => []]);
        $tmdb->shouldReceive('getWatchProviders')
            ->andReturn(['results' => []]);

        (new EnrichTitleJob($title))->handle();

        $this->assertDatabaseCount('title_providers', 0);
    }

    public function test_syncs_the_keywords_tmdb_returns(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 789]);

        $tmdb = $this->mock(TmdbApiService::class);
        $tmdb->shouldReceive('getKeywords')->andReturn(['keywords' => [
            ['id' => 4379, 'name' => 'time travel'],
            ['id' => 9999, 'name' => ' 1985 '],
            ['id' => 4379, 'name' => 'time travel'],
        ]]);
        $tmdb->shouldReceive('getWatchProviders')->andReturn(['results' => []]);

        (new EnrichTitleJob($title))->handle();

        $this->assertEqualsCanonicalizing(['time-travel', '1985'], $title->keywords()->pluck('slug')->all());
        $this->assertFalse($title->keywords()->where('slug', '1985')->value('use_in_similarity'));
    }

    public function test_keeps_existing_keywords_and_stays_due_when_tmdb_errors(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 321, 'enriched_at' => null]);
        $title->keywords()->attach(Keyword::factory()->create());
        Http::fake(['tmdb.test/*' => Http::response(['success' => false, 'status_code' => 43], 503)]);
        $this->app->instance(TmdbApiService::class, new TmdbApiService(apiKey: 'test-key', baseUrl: 'https://tmdb.test'));

        try {
            (new EnrichTitleJob($title))->handle();
            $this->fail('Expected the TMDB failure to propagate.');
        } catch (RequestException) {
        }

        $this->assertSame(1, $title->keywords()->count());
        $this->assertNull($title->fresh()->enriched_at);
    }

    public function test_keeps_keywords_that_look_falsy_or_numeric(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 654]);

        $tmdb = $this->mock(TmdbApiService::class);
        $tmdb->shouldReceive('getKeywords')->andReturn(['keywords' => [
            ['id' => 1, 'name' => '0'],
            ['id' => 2, 'name' => '007'],
            ['id' => 3, 'name' => '7'],
            ['id' => 4, 'name' => '   '],
        ]]);
        $tmdb->shouldReceive('getWatchProviders')->andReturn(['results' => []]);

        (new EnrichTitleJob($title))->handle();

        $this->assertEqualsCanonicalizing(['0', '007', '7'], $title->keywords()->pluck('name')->all());
    }
}
