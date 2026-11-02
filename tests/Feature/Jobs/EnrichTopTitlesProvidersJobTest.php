<?php

namespace Tests\Feature\Jobs;

use App\Domain\Enums\ProviderSource;
use App\Jobs\EnrichTopTitlesProvidersJob;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use App\Services\ProviderServices\ProviderCatalog;
use App\Services\ProviderServices\StreamingAvailabilityService;
use App\Services\ProviderServices\TitleProviderWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnrichTopTitlesProvidersJobTest extends TestCase
{
    use RefreshDatabase;

    private function show(): array
    {
        return [
            'imdbId' => 'tt1234567',
            'tmdbId' => 'movie/1234567',
            'streamingOptions' => ['kr' => [[
                'service' => ['id' => 'netflix'],
                'type' => 'subscription',
                'link' => 'https://netflix.test/1',
                'quality' => 'hd',
            ]]],
        ];
    }

    public function test_upserts_top_titles_for_the_country_ranked_providers_without_duplicates(): void
    {
        $country = Country::factory()->create(['iso2' => 'KR']);
        $netflix = Provider::factory()->withAlias(ProviderSource::STREAMING_AVAILABILITY, 'netflix')->create();
        $disney = Provider::factory()->withAlias(ProviderSource::STREAMING_AVAILABILITY, 'disney')->create();
        CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $netflix->id]);
        $title = Title::factory()->create(['imdb_id' => 'tt1234567']);

        Http::fake(['movie-night.test/*' => Http::response([$this->show()])]);

        $this->app->instance(StreamingAvailabilityService::class, new StreamingAvailabilityService(
            'key', 'https://movie-night.test', $this->app->make(ProviderCatalog::class),
        ));

        (new EnrichTopTitlesProvidersJob($country))->handle($this->app->make(StreamingAvailabilityService::class), new TitleProviderWriter);
        (new EnrichTopTitlesProvidersJob($country))->handle($this->app->make(StreamingAvailabilityService::class), new TitleProviderWriter);

        $this->assertCount(4, Http::recorded());
        $this->assertDatabaseCount('title_providers', 1);
        $row = TitleProvider::first();
        $this->assertSame($title->id, $row->title_id);
        $this->assertSame($netflix->id, $row->provider_id);
        $this->assertSame('https://netflix.test/1', $row->link);
    }
}
