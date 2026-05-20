<?php

namespace Tests\Feature\Service;

use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\Title;
use App\Services\WatchProviderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchProviderSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    // Mirrors the shape TMDB returns for watch/providers
    private function makeResults(string $iso2, string $streamType, array $providers, string $link = 'https://tmdb.org/watch'): array
    {
        return [
            $iso2 => array_merge(['link' => $link], [$streamType => $providers]),
        ];
    }

    private function makeRawProvider(int $tmdbId = 8, string $name = 'Netflix', int $priority = 0): array
    {
        return [
            'provider_id' => $tmdbId,
            'provider_name' => $name,
            'logo_path' => '/logo.png',
            'display_priority' => $priority,
        ];
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_inserts_title_provider_when_provider_and_country_provider_exist(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->create(['tmdb_id' => 8]);
        $countryProvider = CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(8)]);

        (new WatchProviderSyncService)->sync($title, $results);

        $this->assertDatabaseHas('title_providers', [
            'title_id' => $title->id,
            'country_provider_id' => $countryProvider->id,
            'country_id' => $country->id,
            'provider_id' => $provider->id,
            'type' => StreamType::FLATRATE->value,
            'priority' => 0,
        ]);
    }

    public function test_inserts_one_row_per_stream_type_for_same_provider_and_country(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->create(['tmdb_id' => 8]);
        CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $title = Title::factory()->create();

        $raw = $this->makeRawProvider(8);
        $results = [
            'US' => [
                'link' => 'https://tmdb.org/watch',
                'flatrate' => [$raw],
                'rent' => [$raw],
                'buy' => [$raw],
            ],
        ];

        (new WatchProviderSyncService)->sync($title, $results);

        $this->assertDatabaseCount('title_providers', 3);
        foreach (StreamType::cases() as $type) {
            $this->assertDatabaseHas('title_providers', [
                'title_id' => $title->id,
                'type' => $type->value,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Missing provider
    // -------------------------------------------------------------------------

    public function test_creates_provider_when_not_in_database(): void
    {
        Country::factory()->create(['iso2' => 'US']);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(99, 'New Provider')]);

        (new WatchProviderSyncService)->sync($title, $results);

        $this->assertDatabaseHas('providers', ['tmdb_id' => 99, 'name' => 'New Provider']);
    }

    public function test_creates_country_provider_and_title_provider_for_new_provider(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(99, 'New Provider', 3)]);

        (new WatchProviderSyncService)->sync($title, $results);

        $provider = Provider::where('tmdb_id', 99)->sole();

        $this->assertDatabaseHas('country_providers', [
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $this->assertDatabaseHas('title_providers', [
            'title_id' => $title->id,
            'country_id' => $country->id,
            'provider_id' => $provider->id,
            'priority' => 3,
        ]);
    }

    // -------------------------------------------------------------------------
    // Missing country_provider
    // -------------------------------------------------------------------------

    public function test_creates_country_provider_when_provider_exists_but_not_linked_to_country(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->create(['tmdb_id' => 8]);
        // deliberately no CountryProvider
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(8)]);

        (new WatchProviderSyncService)->sync($title, $results);

        $this->assertDatabaseHas('country_providers', [
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $this->assertDatabaseCount('title_providers', 1);
    }

    // -------------------------------------------------------------------------
    // Deduplication
    // -------------------------------------------------------------------------

    public function test_inserts_new_provider_once_even_when_it_appears_in_multiple_countries(): void
    {
        Country::factory()->create(['iso2' => 'US']);
        Country::factory()->create(['iso2' => 'GB']);
        $title = Title::factory()->create();

        $raw = $this->makeRawProvider(99, 'NewFlix');
        $results = [
            'US' => ['link' => 'https://tmdb.org/watch', 'flatrate' => [$raw]],
            'GB' => ['link' => 'https://tmdb.org/watch', 'flatrate' => [$raw]],
        ];

        (new WatchProviderSyncService)->sync($title, $results);

        $this->assertDatabaseCount('providers', 1);
        $this->assertDatabaseCount('country_providers', 2);
        $this->assertDatabaseCount('title_providers', 2);
    }

    public function test_deduplicates_same_provider_listed_twice_in_same_stream_type(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->create(['tmdb_id' => 8]);
        CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $title = Title::factory()->create();

        $raw = $this->makeRawProvider(8);
        $results = [
            'US' => ['link' => 'https://tmdb.org/watch', 'flatrate' => [$raw, $raw]],
        ];

        (new WatchProviderSyncService)->sync($title, $results);

        $this->assertDatabaseCount('title_providers', 1);
    }

    // -------------------------------------------------------------------------
    // Unknown/invalid data
    // -------------------------------------------------------------------------

    public function test_skips_unknown_country_without_error(): void
    {
        $title = Title::factory()->create();

        $results = $this->makeResults('XX', 'flatrate', [$this->makeRawProvider(8)]);

        (new WatchProviderSyncService)->sync($title, $results);

        $this->assertDatabaseCount('title_providers', 0);
    }

    public function test_skips_unknown_stream_type_without_error(): void
    {
        Country::factory()->create(['iso2' => 'US']);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'cinema_only', [$this->makeRawProvider(8)]);

        (new WatchProviderSyncService)->sync($title, $results);

        $this->assertDatabaseCount('title_providers', 0);
    }

    public function test_does_nothing_when_results_are_empty(): void
    {
        $title = Title::factory()->create();

        (new WatchProviderSyncService)->sync($title, []);

        $this->assertDatabaseCount('title_providers', 0);
    }

    // -------------------------------------------------------------------------
    // Idempotency
    // -------------------------------------------------------------------------

    public function test_resync_replaces_existing_title_providers_instead_of_accumulating(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->create(['tmdb_id' => 8]);
        CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(8)]);
        $service = new WatchProviderSyncService;

        $service->sync($title, $results);
        $this->assertDatabaseCount('title_providers', 1);

        $service->sync($title, $results);
        $this->assertDatabaseCount('title_providers', 1);
    }

    public function test_resync_with_no_matching_data_removes_existing_title_providers(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->create(['tmdb_id' => 8]);
        CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $title = Title::factory()->create();
        $otherTitle = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(8)]);
        $service = new WatchProviderSyncService;

        $service->sync($title, $results);
        $service->sync($otherTitle, $results);
        $this->assertDatabaseCount('title_providers', 2);

        // Second sync for $title with new data: only $otherTitle's row should remain
        $service->sync($title, $this->makeResults('US', 'rent', [$this->makeRawProvider(8)]));
        $this->assertDatabaseCount('title_providers', 2);
        $this->assertDatabaseHas('title_providers', ['title_id' => $title->id, 'type' => StreamType::RENT->value]);
        $this->assertDatabaseMissing('title_providers', ['title_id' => $title->id, 'type' => StreamType::FLATRATE->value]);
    }
}
