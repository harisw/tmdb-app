<?php

namespace Tests\Feature\Service\ProviderServices;

use App\Data\DTOs\ProviderData;
use App\Domain\Enums\ProviderSource;
use App\Models\Provider;
use App\Models\ProviderAlias;
use App\Services\ProviderServices\ProviderCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function tmdbProvider(int $tmdbId, string $name): ProviderData
    {
        return ProviderData::from([
            'provider_id' => $tmdbId,
            'provider_name' => $name,
            'logo_path' => '/logo.png',
            'display_priority' => 3,
        ]);
    }

    public function test_finds_a_provider_by_any_source_alias(): void
    {
        $netflix = Provider::factory()->withAlias(ProviderSource::WATCHMODE, 'Netflix')->create();

        $this->assertSame($netflix->id, (new ProviderCatalog)->providerId(ProviderSource::WATCHMODE, 'Netflix'));
    }

    public function test_returns_null_for_an_unknown_third_party_alias(): void
    {
        $this->assertNull((new ProviderCatalog)->providerId(ProviderSource::STREAMING_AVAILABILITY, 'unknown'));
    }

    public function test_resolves_a_known_tmdb_id_without_writing(): void
    {
        $disney = Provider::factory()->withTmdbId(337)->create(['name' => 'Disney Plus']);

        $providerId = (new ProviderCatalog)->findOrCreateTmdbProvider($this->tmdbProvider(337, 'Disney Plus'));

        $this->assertSame($disney->id, $providerId);
        $this->assertDatabaseCount('providers', 1);
        $this->assertDatabaseCount('provider_aliases', 1);
    }

    public function test_aliases_an_unknown_tmdb_id_to_the_provider_with_the_same_normalized_name(): void
    {
        $disney = Provider::factory()->withTmdbId(337)->create(['name' => 'Disney Plus']);

        $providerId = (new ProviderCatalog)->findOrCreateTmdbProvider($this->tmdbProvider(390, 'Disney+'));

        $this->assertSame($disney->id, $providerId);
        $this->assertDatabaseCount('providers', 1);
        $this->assertDatabaseHas('provider_aliases', [
            'provider_id' => $disney->id,
            'source' => ProviderSource::TMDB->value,
            'external_id' => '390',
            'name' => 'Disney+',
        ]);
    }

    public function test_aliases_an_unknown_tmdb_id_matching_a_former_name_kept_on_an_alias(): void
    {
        $lionsgate = Provider::factory()->create(['name' => 'Lionsgate Play']);
        ProviderAlias::factory()->for($lionsgate)->create(['external_id' => '2358', 'name' => 'Lionsgate+ Amazon Channels']);

        $providerId = (new ProviderCatalog)->findOrCreateTmdbProvider($this->tmdbProvider(9999, 'Lionsgate+ Amazon Channels'));

        $this->assertSame($lionsgate->id, $providerId);
        $this->assertDatabaseCount('providers', 1);
    }

    public function test_creates_a_provider_for_an_unknown_tmdb_id_with_an_unmatched_name(): void
    {
        $providerId = (new ProviderCatalog)->findOrCreateTmdbProvider($this->tmdbProvider(2358, 'Lionsgate+ Amazon Channel'));

        $provider = Provider::findOrFail($providerId);
        $this->assertSame('lionsgate-amazon-channel', $provider->slug);
        $this->assertSame('Lionsgate+ Amazon Channel', $provider->name);
        $this->assertSame('/logo.png', $provider->img_logo);
        $this->assertSame(3, $provider->priority);
        $this->assertDatabaseHas('provider_aliases', ['provider_id' => $providerId, 'external_id' => '2358']);
    }

    public function test_keeps_providers_apart_when_only_their_slugs_collide(): void
    {
        $amcPlus = Provider::factory()->withTmdbId(526)->create(['name' => 'AMC+', 'slug' => 'amc']);

        $providerId = (new ProviderCatalog)->findOrCreateTmdbProvider($this->tmdbProvider(80, 'AMC'));

        $this->assertNotSame($amcPlus->id, $providerId);
        $this->assertSame('amc-80', Provider::findOrFail($providerId)->slug);
    }

    public function test_reuses_a_provider_another_worker_created_after_the_catalog_was_loaded(): void
    {
        $catalog = new ProviderCatalog;
        $created = Provider::factory()->withTmdbId(2358)->create(['name' => 'Something Else']);

        $providerId = $catalog->findOrCreateTmdbProvider($this->tmdbProvider(2358, 'Lionsgate+ Amazon Channel'));

        $this->assertSame($created->id, $providerId);
        $this->assertDatabaseCount('providers', 1);
    }

    public function test_remembers_providers_it_created_within_the_same_job(): void
    {
        $catalog = new ProviderCatalog;

        $first = $catalog->findOrCreateTmdbProvider($this->tmdbProvider(99, 'NewFlix'));
        $second = $catalog->findOrCreateTmdbProvider($this->tmdbProvider(99, 'NewFlix'));

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('providers', 1);
    }

    public function test_normalizes_plus_signs_spacing_and_case(): void
    {
        $this->assertSame('crime plus investigation', ProviderCatalog::normalizeName('Crime+Investigation'));
        $this->assertSame('crime plus investigation', ProviderCatalog::normalizeName(' CRIME+ Investigation '));
        $this->assertSame('discovery plus', ProviderCatalog::normalizeName('Discovery +'));
        $this->assertNotSame(ProviderCatalog::normalizeName('AMC'), ProviderCatalog::normalizeName('AMC+'));
    }
}
