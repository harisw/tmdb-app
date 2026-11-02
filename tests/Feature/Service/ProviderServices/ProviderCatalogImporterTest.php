<?php

namespace Tests\Feature\Service\ProviderServices;

use App\Data\DTOs\ProviderData;
use App\Domain\Enums\ProviderSource;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use App\Services\ProviderServices\ProviderCatalog;
use App\Services\ProviderServices\ProviderCatalogImporter;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use Tests\TestCase;

class ProviderCatalogImporterTest extends TestCase
{
    use RefreshDatabase;

    private function record(int $tmdbId, string $name, array $displayPriorities = [], string $logo = '/logo.png'): ProviderData
    {
        return ProviderData::from([
            'provider_id' => $tmdbId,
            'provider_name' => $name,
            'logo_path' => $logo,
            'display_priority' => 5,
            'display_priorities' => $displayPriorities,
        ]);
    }

    private function importer(): ProviderCatalogImporter
    {
        return app(ProviderCatalogImporter::class);
    }

    private function providerWithTmdbId(int $tmdbId): Provider
    {
        return Provider::whereRelation('aliases', 'external_id', (string) $tmdbId)->sole();
    }

    public function test_creates_one_provider_per_name_with_the_last_record_winning(): void
    {
        $this->importer()->import(collect([
            $this->record(337, 'Disney Plus', logo: '/first.png'),
            $this->record(390, 'Disney+', logo: '/last.png'),
        ]));

        $provider = Provider::sole();
        $this->assertSame('disney', $provider->slug);
        $this->assertSame('Disney+', $provider->name);
        $this->assertSame('/last.png', $provider->img_logo);
        $this->assertEqualsCanonicalizing(['337', '390'], $provider->aliases()->pluck('external_id')->all());
    }

    public function test_combines_a_repeated_tmdb_id_with_the_later_record_winning_and_the_best_priority_kept(): void
    {
        $us = Country::factory()->create(['iso2' => 'US']);

        $this->importer()->import(collect([
            $this->record(8, 'Netflix', ['US' => 2], logo: '/movie.png'),
            $this->record(8, 'Netflix', ['US' => 5], logo: '/tv.png'),
        ]));

        $provider = Provider::sole();
        $this->assertSame('/tv.png', $provider->img_logo);
        $this->assertDatabaseCount('provider_aliases', 1);
        $this->assertDatabaseHas('country_providers', ['country_id' => $us->id, 'provider_id' => $provider->id, 'priority' => 2]);
    }

    public function test_waits_for_the_catalog_to_finish_creating_a_provider(): void
    {
        Sleep::fake(syncWithCarbon: true);
        Cache::lock(ProviderCatalog::TMDB_LOCK)->get();

        try {
            $this->importer()->import(collect([$this->record(8, 'Netflix')]));
            $this->fail('Expected the import to wait for the catalog lock');
        } catch (LockTimeoutException) {
            $this->assertDatabaseCount('providers', 0);
        }
    }

    public function test_clears_the_cached_top_providers(): void
    {
        Cache::forever('top-providers', []);

        $this->importer()->import(collect([$this->record(8, 'Netflix')]));

        $this->assertFalse(Cache::has('top-providers'));
    }

    public function test_folds_curated_variants_into_their_main_provider(): void
    {
        $this->importer()->import(
            collect([$this->record(119, 'Amazon Prime Video'), $this->record(2100, 'Amazon Prime Video with Ads')]),
            tmdbVariants: [2100 => 119],
        );

        $provider = Provider::sole();
        $this->assertSame('Amazon Prime Video', $provider->name);
        $this->assertSame($provider->id, $this->providerWithTmdbId(2100)->id);
    }

    public function test_merges_providers_that_already_exist_separately_for_one_group(): void
    {
        $country = Country::factory()->create();
        $main = Provider::factory()->withTmdbId(119)->create(['slug' => 'amazon-prime-video']);
        $variant = Provider::factory()->withTmdbId(2100)->create(['slug' => 'amazon-prime-video-with-ads']);
        $variantLink = CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $variant->id]);
        $offer = TitleProvider::factory()->create([
            'country_provider_id' => $variantLink->id,
            'country_id' => $country->id,
            'provider_id' => $variant->id,
            'title_id' => Title::factory(),
        ]);

        $this->importer()->import(
            collect([$this->record(119, 'Amazon Prime Video'), $this->record(2100, 'Amazon Prime Video with Ads')]),
            tmdbVariants: [2100 => 119],
        );

        $this->assertModelMissing($variant);
        $this->assertSame($main->id, $offer->fresh()->provider_id);
    }

    public function test_keeps_the_slug_of_an_existing_provider_and_refreshes_its_name(): void
    {
        $existing = Provider::factory()->withTmdbId(2358)->create(['slug' => 'lionsgate-amazon-channels', 'name' => 'Old']);

        $this->importer()->import(collect([$this->record(2358, 'Lionsgate+ Amazon Channel')]));

        $existing->refresh();
        $this->assertSame('lionsgate-amazon-channels', $existing->slug);
        $this->assertSame('Lionsgate+ Amazon Channel', $existing->name);
    }

    public function test_suffixes_the_slug_of_a_new_provider_that_collides_with_another(): void
    {
        $this->importer()->import(collect([$this->record(526, 'AMC+'), $this->record(80, 'AMC')]));

        $this->assertSame('amc', $this->providerWithTmdbId(526)->slug);
        $this->assertSame('amc-80', $this->providerWithTmdbId(80)->slug);
    }

    public function test_links_countries_with_the_best_priority_across_a_group(): void
    {
        $us = Country::factory()->create(['iso2' => 'US']);

        $this->importer()->import(collect([
            $this->record(337, 'Disney Plus', ['US' => 9, 'ZZ' => 1]),
            $this->record(390, 'Disney Plus', ['US' => 4]),
        ]));

        $this->assertDatabaseCount('country_providers', 1);
        $this->assertDatabaseHas('country_providers', [
            'country_id' => $us->id,
            'provider_id' => Provider::sole()->id,
            'priority' => 4,
        ]);
    }

    public function test_aliases_third_party_names_to_the_provider_of_a_tmdb_id(): void
    {
        $this->importer()->import(
            collect([$this->record(119, 'Amazon Prime Video'), $this->record(10, 'Amazon Video')]),
            tmdbVariants: [10 => 119],
            externalAliases: [ProviderSource::WATCHMODE->value => ['Amazon' => 10]],
        );

        $this->assertDatabaseHas('provider_aliases', [
            'provider_id' => Provider::sole()->id,
            'source' => ProviderSource::WATCHMODE->value,
            'external_id' => 'Amazon',
        ]);
    }

    public function test_fails_when_a_third_party_alias_points_at_an_unknown_tmdb_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->importer()->import(
            collect([$this->record(8, 'Netflix')]),
            externalAliases: [ProviderSource::WATCHMODE->value => ['Amazon' => 10]],
        );
    }

    public function test_importing_twice_changes_nothing(): void
    {
        Country::factory()->create(['iso2' => 'US']);
        $records = collect([$this->record(337, 'Disney Plus', ['US' => 4]), $this->record(390, 'Disney Plus')]);

        $this->importer()->import($records);
        $this->importer()->import($records);

        $this->assertDatabaseCount('providers', 1);
        $this->assertDatabaseCount('provider_aliases', 2);
        $this->assertDatabaseCount('country_providers', 1);
    }
}
