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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProviderCatalogImporter
{
    /** @var Collection<int, int> */
    private Collection $providerIdsByTmdbId;

    /** @var Collection<string, bool> */
    private Collection $usedSlugs;

    /** @var Collection<string, int> */
    private Collection $countryIdsByIso2;

    public function __construct(private readonly ProviderMerger $merger) {}

    /**
     * @param  Collection<int, ProviderData>  $providers  may repeat a TMDB id; later ones take precedence
     * @param  array<int, int>  $tmdbVariants  variant TMDB id => main TMDB id
     * @param  array<string, array<string, int>>  $externalAliases  source => [external id => TMDB id]
     */
    public function import(Collection $providers, array $tmdbVariants = [], array $externalAliases = []): void
    {
        Cache::lock(ProviderCatalog::TMDB_LOCK, 300)->block(30, fn () => DB::transaction(function () use ($providers, $tmdbVariants, $externalAliases) {
            $this->loadLookups();
            $this->countryIdsByIso2 = Country::pluck('id', 'iso2');

            foreach ($this->groups($providers, $tmdbVariants) as $nameKey => $members) {
                $providerId = $this->upsertProvider($this->canonical($nameKey, $members), $members);
                $this->upsertTmdbAliases($providerId, $members);
                $this->linkCountries($providerId, $members);
            }

            $this->upsertExternalAliases($externalAliases);
        }));

        (new Provider)->flushCache();
        (new CountryProvider)->flushCache();
        Cache::forget('top-providers');
    }

    /**
     * @param  Collection<int, ProviderData>  $providers
     * @return Collection<string, Collection<int, ProviderData>>
     */
    private function groups(Collection $providers, array $tmdbVariants): Collection
    {
        $latestByTmdbId = $providers->keyBy('tmdb_id');

        return $providers->groupBy(function (ProviderData $provider) use ($latestByTmdbId, $tmdbVariants) {
            $mainTmdbId = $tmdbVariants[$provider->tmdb_id] ?? $provider->tmdb_id;
            $main = $latestByTmdbId->get($mainTmdbId) ?? throw new InvalidArgumentException("Unknown TMDB provider [{$mainTmdbId}]");

            return ProviderCatalog::normalizeName($main->name);
        });
    }

    /**
     * @param  Collection<int, ProviderData>  $members
     */
    private function canonical(string $nameKey, Collection $members): ProviderData
    {
        return $members->last(fn (ProviderData $member) => ProviderCatalog::normalizeName($member->name) === $nameKey)
            ?? $members->last();
    }

    /**
     * @param  Collection<int, ProviderData>  $members
     */
    private function upsertProvider(ProviderData $canonical, Collection $members): int
    {
        $existingIds = $members
            ->map(fn (ProviderData $member) => $this->providerIdsByTmdbId->get($member->tmdb_id))
            ->filter(fn (?int $id) => $id !== null)
            ->unique();
        $targetId = $this->providerIdsByTmdbId->get($canonical->tmdb_id) ?? $existingIds->first();
        if (! $targetId) {
            return $this->createProvider($canonical);
        }

        $target = Provider::disableCache()->findOrFail($targetId);
        $target->update($this->attributes($canonical));
        $this->mergeInto($target, $existingIds->reject(fn (int $id) => $id === $targetId));

        return $targetId;
    }

    private function createProvider(ProviderData $provider): int
    {
        $slug = Str::slug($provider->name);
        $slug = $this->usedSlugs->has($slug) ? "{$slug}-{$provider->tmdb_id}" : $slug;
        $this->usedSlugs->put($slug, true);

        return Provider::create(['slug' => $slug, ...$this->attributes($provider)])->id;
    }

    /**
     * @return array{name: string, img_logo: ?string, priority: int}
     */
    private function attributes(ProviderData $provider): array
    {
        return [
            'name' => $provider->name,
            'img_logo' => $provider->img_logo,
            'priority' => $provider->priority,
        ];
    }

    /**
     * @param  Collection<int, int>  $providerIds
     */
    private function mergeInto(Provider $target, Collection $providerIds): void
    {
        if ($providerIds->isEmpty()) {
            return;
        }

        foreach ($providerIds as $providerId) {
            $this->merger->merge(Provider::disableCache()->findOrFail($providerId), $target);
        }
        $this->loadLookups();
    }

    /**
     * @param  Collection<int, ProviderData>  $members
     */
    private function upsertTmdbAliases(int $providerId, Collection $members): void
    {
        $this->upsertAliases($members->keyBy('tmdb_id')->map(fn (ProviderData $member) => [
            'provider_id' => $providerId,
            'source' => ProviderSource::TMDB->value,
            'external_id' => (string) $member->tmdb_id,
            'name' => $member->name,
        ])->values()->all());

        foreach ($members as $member) {
            $this->providerIdsByTmdbId->put($member->tmdb_id, $providerId);
        }
    }

    /**
     * @param  array<string, array<string, int>>  $externalAliases
     */
    private function upsertExternalAliases(array $externalAliases): void
    {
        $rows = [];
        foreach ($externalAliases as $source => $tmdbIdsByExternalId) {
            foreach ($tmdbIdsByExternalId as $externalId => $tmdbId) {
                $rows[] = [
                    'provider_id' => $this->providerIdsByTmdbId->get($tmdbId)
                        ?? throw new InvalidArgumentException("[{$source}:{$externalId}] points at unknown TMDB provider [{$tmdbId}]"),
                    'source' => ProviderSource::from($source)->value,
                    'external_id' => (string) $externalId,
                    'name' => (string) $externalId,
                ];
            }
        }

        $this->upsertAliases($rows);
    }

    /**
     * @param  Collection<int, ProviderData>  $members
     */
    private function linkCountries(int $providerId, Collection $members): void
    {
        $prioritiesByCountryId = [];
        foreach ($members as $member) {
            foreach ($member->display_priorities as $iso2 => $priority) {
                $countryId = $this->countryIdsByIso2->get($iso2);
                if ($countryId) {
                    $prioritiesByCountryId[$countryId] = min($priority, $prioritiesByCountryId[$countryId] ?? $priority);
                }
            }
        }

        CountryProvider::upsert(
            collect($prioritiesByCountryId)->map(fn (int $priority, int $countryId) => [
                'country_id' => $countryId,
                'provider_id' => $providerId,
                'priority' => $priority,
            ])->values()->all(),
            ['country_id', 'provider_id'],
            ['priority'],
        );
    }

    /**
     * @param  list<array{provider_id: int, source: string, external_id: string, name: string}>  $rows
     */
    private function upsertAliases(array $rows): void
    {
        ProviderAlias::upsert($rows, ['source', 'external_id'], ['provider_id', 'name']);
    }

    private function loadLookups(): void
    {
        $this->providerIdsByTmdbId = ProviderAlias::where('source', ProviderSource::TMDB)->pluck('provider_id', 'external_id');
        $this->usedSlugs = Provider::disableCache()->pluck('slug')->mapWithKeys(fn (string $slug) => [$slug => true]);
    }
}
