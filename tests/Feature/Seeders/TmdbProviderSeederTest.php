<?php

namespace Tests\Feature\Seeders;

use App\Domain\Enums\ProviderSource;
use App\Models\Country;
use App\Models\Provider;
use App\Models\ProviderAlias;
use Database\Seeders\TmdbProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TmdbProviderSeederTest extends TestCase
{
    use RefreshDatabase;

    private function provider(ProviderSource $source, int|string $externalId): Provider
    {
        return ProviderAlias::where('source', $source)->where('external_id', (string) $externalId)->sole()->provider;
    }

    private function tmdbIds(Provider $provider): array
    {
        return $provider->aliases()->where('source', ProviderSource::TMDB)->pluck('external_id')->map(fn ($id) => (int) $id)->all();
    }

    public function test_seeds_one_provider_per_service_from_the_tmdb_snapshots(): void
    {
        Country::factory()->create(['iso2' => 'US']);
        Cache::forever('top-providers', []);

        $this->seed(TmdbProviderSeeder::class);

        $disney = $this->provider(ProviderSource::TMDB, 337);
        $this->assertSame('disney-plus', $disney->slug);
        $this->assertSame('Disney Plus', $disney->name);
        $this->assertEqualsCanonicalizing([122, 337, 390], $this->tmdbIds($disney));

        $prime = $this->provider(ProviderSource::TMDB, 119);
        $this->assertSame('amazon-prime-video', $prime->slug);
        $this->assertEqualsCanonicalizing([9, 10, 119, 613, 2100], $this->tmdbIds($prime));
        $this->assertTrue($prime->is($this->provider(ProviderSource::WATCHMODE, 'Amazon')));
        $this->assertTrue($prime->is($this->provider(ProviderSource::STREAMING_AVAILABILITY, 'prime')));

        $this->assertTrue($this->provider(ProviderSource::TMDB, 561)->is($this->provider(ProviderSource::TMDB, 2358)));
        $this->assertFalse($this->provider(ProviderSource::TMDB, 80)->is($this->provider(ProviderSource::TMDB, 526)));
        $this->assertFalse($this->provider(ProviderSource::WATCHMODE, 'AppleTV')->is($this->provider(ProviderSource::STREAMING_AVAILABILITY, 'apple')));
        $this->assertTrue($this->provider(ProviderSource::STREAMING_AVAILABILITY, 'apple')->is($this->provider(ProviderSource::TMDB, 350)));

        $this->assertGreaterThan(0, $prime->countryProviders()->count());
        $this->assertFalse(Cache::has('top-providers'));
    }

    public function test_seeding_again_changes_nothing(): void
    {
        $this->seed(TmdbProviderSeeder::class);
        $counts = [Provider::count(), ProviderAlias::count()];

        $this->seed(TmdbProviderSeeder::class);

        $this->assertSame($counts, [Provider::count(), ProviderAlias::count()]);
    }
}
