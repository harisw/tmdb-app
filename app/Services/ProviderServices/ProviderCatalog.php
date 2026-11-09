<?php

namespace App\Services\ProviderServices;

use App\Data\DTOs\ProviderData;
use App\Domain\Enums\ProviderSource;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\ProviderAlias;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProviderCatalog
{
    public const TMDB_LOCK = 'provider-catalog:tmdb';

    /** @var Collection<string, int> */
    private Collection $providerIdsByAlias;

    private Collection $countryIdsByIso2;

    /** @var array<int, Collection<int, int>> */
    private array $countryProviderIdsByCountry = [];

    public function __construct()
    {
        $this->providerIdsByAlias = ProviderAlias::get(['source', 'external_id', 'provider_id'])
            ->mapWithKeys(fn (ProviderAlias $alias) => [$this->aliasKey($alias->source, $alias->external_id) => $alias->provider_id]);
        $this->countryIdsByIso2 = Country::pluck('id', 'iso2');
    }

    public static function normalizeName(string $name): string
    {
        return Str::of($name)
            ->ascii()
            ->lower()
            ->replace('+', ' plus ')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();
    }

    public function providerId(ProviderSource $source, ?string $externalId): ?int
    {
        $providerId = $externalId === null ? null : $this->providerIdsByAlias->get($this->aliasKey($source, $externalId));
        if (! $providerId) {
            Log::warning("Provider alias [{$source->value}:{$externalId}] not found");
        }

        return $providerId;
    }

    public function findOrCreateTmdbProvider(ProviderData $provider): int
    {
        return $this->providerIdsByAlias->get($this->aliasKey(ProviderSource::TMDB, $provider->tmdb_id))
            ?? Cache::lock(self::TMDB_LOCK, 10)->block(5, fn () => $this->createTmdbAlias($provider));
    }

    public function countryProviderId(int $countryId, int $providerId): ?int
    {
        $this->countryProviderIdsByCountry[$countryId] ??= CountryProvider::where('country_id', $countryId)
            ->pluck('id', 'provider_id');

        $countryProviderId = $this->countryProviderIdsByCountry[$countryId]->get($providerId);
        if (! $countryProviderId) {
            Log::warning("Provider [$providerId] is not linked to country [$countryId]");
        }

        return $countryProviderId;
    }

    public function countryId(string $iso2): ?int
    {
        $countryId = $this->countryIdsByIso2->get(strtoupper($iso2));
        if (! $countryId) {
            Log::warning("Country [$iso2] not found");
        }

        return $countryId;
    }

    private function createTmdbAlias(ProviderData $provider): int
    {
        $alias = ['source' => ProviderSource::TMDB, 'external_id' => (string) $provider->tmdb_id];
        $providerId = ProviderAlias::where($alias)->value('provider_id');

        if (! $providerId) {
            $matchedProviderId = $this->providerIdWithName($provider->name);
            $providerId = $matchedProviderId ?? $this->createProvider($provider)->id;
            ProviderAlias::create([...$alias, 'provider_id' => $providerId, 'name' => $provider->name]);

            Log::info($matchedProviderId ? 'Aliased TMDB provider by name' : 'Created provider for TMDB provider', [
                'tmdb_id' => $provider->tmdb_id,
                'name' => $provider->name,
                'provider_id' => $providerId,
            ]);
        }

        $this->providerIdsByAlias->put($this->aliasKey(ProviderSource::TMDB, $provider->tmdb_id), $providerId);

        return $providerId;
    }

    private function providerIdWithName(string $name): ?int
    {
        $normalizedName = self::normalizeName($name);
        $matches = fn (?string $candidate) => $candidate !== null && self::normalizeName($candidate) === $normalizedName;

        return Provider::disableCache()->get(['id', 'name'])->first(fn (Provider $p) => $matches($p->name))->id
            ?? ProviderAlias::get(['provider_id', 'name'])->first(fn (ProviderAlias $a) => $matches($a->name))?->provider_id;
    }

    private function createProvider(ProviderData $provider): Provider
    {
        $slug = Str::slug($provider->name);

        return Provider::create([
            'slug' => Provider::disableCache()->where('slug', $slug)->exists() ? "{$slug}-{$provider->tmdb_id}" : $slug,
            'name' => $provider->name,
            'img_logo' => $provider->img_logo,
            'priority' => $provider->priority,
        ]);
    }

    private function aliasKey(ProviderSource $source, int|string $externalId): string
    {
        return "{$source->value}:{$externalId}";
    }
}
