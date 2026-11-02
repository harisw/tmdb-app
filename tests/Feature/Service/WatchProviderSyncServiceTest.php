<?php

namespace Tests\Feature\Service;

use App\Domain\Enums\ProviderSource;
use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\Title;
use App\Services\ProviderServices\ProviderCatalog;
use App\Services\WatchProviderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchProviderSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WatchProviderSyncService
    {
        return app(WatchProviderSyncService::class);
    }

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

    public function test_inserts_title_provider_when_provider_and_country_provider_exist(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->withTmdbId(8)->create(['slug' => 'netflix']);
        $countryProvider = CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(8)]);

        $this->service()->sync($title, $results);

        $this->assertDatabaseHas('title_providers', [
            'title_id' => $title->id,
            'country_provider_id' => $countryProvider->id,
            'country_id' => $country->id,
            'provider_id' => $provider->id,
            'type' => StreamType::FLATRATE->value,
            'priority' => 0,
        ]);
    }

    public function test_creates_provider_country_provider_and_title_provider_for_unknown_provider(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(99, 'New Provider', 3)]);

        $this->service()->sync($title, $results);

        $provider = Provider::whereRelation('aliases', 'external_id', '99')->sole();

        $this->assertSame('New Provider', $provider->name);
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

    public function test_creates_country_provider_when_provider_exists_but_not_linked_to_country(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->withTmdbId(8)->create(['slug' => 'netflix']);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(8)]);

        $this->service()->sync($title, $results);

        $this->assertDatabaseHas('country_providers', [
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $this->assertDatabaseCount('title_providers', 1);
    }

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

        $this->service()->sync($title, $results);

        $this->assertDatabaseCount('providers', 1);
        $this->assertDatabaseCount('country_providers', 2);
        $this->assertDatabaseCount('title_providers', 2);
    }

    public function test_deduplicates_same_provider_listed_twice_in_same_stream_type(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->withTmdbId(8)->create(['slug' => 'netflix']);
        CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $title = Title::factory()->create();

        $raw = $this->makeRawProvider(8);
        $results = [
            'US' => ['link' => 'https://tmdb.org/watch', 'flatrate' => [$raw, $raw]],
        ];

        $this->service()->sync($title, $results);

        $this->assertDatabaseCount('title_providers', 1);
    }

    public function test_skips_unknown_country_without_error(): void
    {
        $title = Title::factory()->create();

        $results = $this->makeResults('XX', 'flatrate', [$this->makeRawProvider(8)]);

        $this->service()->sync($title, $results);

        $this->assertDatabaseCount('title_providers', 0);
    }

    public function test_skips_unknown_stream_type_without_error(): void
    {
        Country::factory()->create(['iso2' => 'US']);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'cinema_only', [$this->makeRawProvider(8)]);

        $this->service()->sync($title, $results);

        $this->assertDatabaseCount('title_providers', 0);
    }

    public function test_resync_replaces_existing_title_providers_instead_of_accumulating(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->withTmdbId(8)->create(['slug' => 'netflix']);
        CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(8)]);
        $service = $this->service();

        $service->sync($title, $results);
        $service->sync($title, $results);
        $this->assertDatabaseCount('title_providers', 1);
    }

    public function test_resync_replaces_only_the_synced_titles_rows(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->withTmdbId(8)->create(['slug' => 'netflix']);
        CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $title = Title::factory()->create();
        $otherTitle = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(8)]);
        $service = $this->service();

        $service->sync($title, $results);
        $service->sync($otherTitle, $results);

        $service->sync($title, $this->makeResults('US', 'rent', [$this->makeRawProvider(8)]));
        $this->assertDatabaseCount('title_providers', 2);
        $this->assertDatabaseHas('title_providers', ['title_id' => $title->id, 'type' => StreamType::RENT->value]);
        $this->assertDatabaseMissing('title_providers', ['title_id' => $title->id, 'type' => StreamType::FLATRATE->value]);
    }

    public function test_links_existing_provider_when_tmdb_renamed_it(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->withTmdbId(2358)->create([
            'slug' => 'lionsgate-play-amazon-channel',
            'name' => 'Lionsgate Play Amazon Channel',
        ]);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(2358, 'Lionsgate+ Amazon Channel')]);

        $this->service()->sync($title, $results);

        $this->assertDatabaseCount('providers', 1);
        $this->assertSame('lionsgate-play-amazon-channel', $provider->fresh()->slug);
        $this->assertDatabaseHas('title_providers', [
            'title_id' => $title->id,
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
    }

    public function test_links_existing_provider_when_another_tmdb_id_shares_its_name(): void
    {
        Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->withTmdbId(119)->create([
            'slug' => 'amazon-prime-video',
            'name' => 'Amazon Prime Video',
        ]);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(9, 'Amazon Prime Video')]);

        $this->service()->sync($title, $results);

        $this->assertDatabaseCount('providers', 1);
        $this->assertEqualsCanonicalizing(['119', '9'], $provider->aliases()->pluck('external_id')->all());
        $this->assertDatabaseHas('title_providers', ['title_id' => $title->id, 'provider_id' => $provider->id]);
    }

    public function test_inserts_one_provider_when_new_tmdb_ids_share_a_name(): void
    {
        Country::factory()->create(['iso2' => 'US']);
        $title = Title::factory()->create();

        $results = $this->makeResults('US', 'flatrate', [
            $this->makeRawProvider(510, 'Discovery Plus'),
            $this->makeRawProvider(520, 'Discovery Plus'),
        ]);

        $this->service()->sync($title, $results);

        $this->assertDatabaseCount('providers', 1);
        $this->assertDatabaseCount('title_providers', 1);
    }

    public function test_new_providers_are_visible_to_the_next_jobs_provider_catalog(): void
    {
        Country::factory()->create(['iso2' => 'US']);
        $title = Title::factory()->create();
        $this->assertNull(app(ProviderCatalog::class)->providerId(ProviderSource::TMDB, '99'));

        $results = $this->makeResults('US', 'flatrate', [$this->makeRawProvider(99, 'NewFlix')]);
        $this->service()->sync($title, $results);
        app()->forgetScopedInstances();

        $this->assertSame(
            Provider::whereRelation('aliases', 'external_id', '99')->value('id'),
            app(ProviderCatalog::class)->providerId(ProviderSource::TMDB, '99'),
        );
    }
}
