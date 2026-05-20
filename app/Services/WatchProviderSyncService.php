<?php

namespace App\Services;

use App\Data\DTOs\ProviderData;
use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WatchProviderSyncService
{
    private Collection $countryMap;

    private Collection $providersMap;

    private Collection $countryProvidersMap;

    public function __construct()
    {
        $this->countryMap = Country::pluck('id', 'iso2');
        $this->providersMap = Provider::pluck('id', 'tmdb_id');
        $this->countryProvidersMap = CountryProvider::select('id', 'country_id', 'provider_id')
            ->get()
            ->keyBy(fn ($cp) => "{$cp->country_id}-{$cp->provider_id}");
    }

    public function sync(Title $title, array $results): void
    {
        $validStreamTypes = StreamType::values();

        $missingProviders = $this->findMissingProviders($results, $validStreamTypes);
        if ($missingProviders) {
            Provider::upsert(array_values($missingProviders), ['tmdb_id'], ['name', 'slug', 'img_logo', 'priority']);
            $this->providersMap = Provider::pluck('id', 'tmdb_id');
        }

        $missingCountryProviders = $this->findMissingCountryProviders($results, $validStreamTypes);
        if ($missingCountryProviders) {
            CountryProvider::upsert(array_values($missingCountryProviders), ['country_id', 'provider_id'], ['priority']);
            $this->countryProvidersMap = CountryProvider::select('id', 'country_id', 'provider_id')
                ->get()
                ->keyBy(fn ($cp) => "{$cp->country_id}-{$cp->provider_id}");
        }

        $rows = $this->buildTitleProviderRows($title, $results, $validStreamTypes);
        $this->persistTitleProviders($title->id, $rows);
    }

    private function findMissingProviders(array $results, array $validStreamTypes): array
    {
        $missing = [];

        foreach ($results as $iso2 => $countryData) {
            if (! $this->countryMap->has($iso2)) {
                Log::warning(self::class.": skipping unknown country [{$iso2}]");

                continue;
            }

            unset($countryData['link']);
            foreach ($countryData as $streamType => $providers) {
                if (! in_array($streamType, $validStreamTypes, true)) {
                    Log::warning(self::class.": skipping unknown stream type [{$streamType}]");

                    continue;
                }

                foreach (collect($providers)->unique('provider_id') as $raw) {
                    $tmdbId = $raw['provider_id'];
                    if (! $this->providersMap->has($tmdbId)) {
                        $missing[$tmdbId] = ProviderData::from($raw)->toUpsertArray();
                    }
                }
            }
        }

        return $missing;
    }

    private function findMissingCountryProviders(array $results, array $validStreamTypes): array
    {
        $missing = [];

        foreach ($results as $iso2 => $countryData) {
            $countryId = $this->countryMap->get($iso2);
            if (! $countryId) {
                continue;
            }

            unset($countryData['link']);
            foreach ($countryData as $streamType => $providers) {
                if (! in_array($streamType, $validStreamTypes, true)) {
                    continue;
                }

                foreach (collect($providers)->unique('provider_id') as $raw) {
                    $providerId = $this->providersMap->get($raw['provider_id']);
                    if (! $providerId) {
                        continue;
                    }

                    $key = "{$countryId}-{$providerId}";
                    if (! $this->countryProvidersMap->has($key)) {
                        $missing[$key] = [
                            'country_id' => $countryId,
                            'provider_id' => $providerId,
                            'priority' => $raw['display_priority'],
                        ];
                    }
                }
            }
        }

        return $missing;
    }

    private function buildTitleProviderRows(Title $title, array $results, array $validStreamTypes): array
    {
        $rows = [];
        $now = now();

        foreach ($results as $iso2 => $countryData) {
            $countryId = $this->countryMap->get($iso2);
            if (! $countryId) {
                continue;
            }

            $tmdbLink = $countryData['link'];
            unset($countryData['link']);

            foreach ($countryData as $streamType => $providers) {
                if (! in_array($streamType, $validStreamTypes, true)) {
                    continue;
                }

                foreach (collect($providers)->unique('provider_id') as $raw) {
                    $providerId = $this->providersMap->get($raw['provider_id']);
                    if (! $providerId) {
                        continue;
                    }

                    $countryProvider = $this->countryProvidersMap->get("{$countryId}-{$providerId}");
                    if (! $countryProvider) {
                        continue;
                    }

                    $rows[] = [
                        'title_id' => $title->id,
                        'country_provider_id' => $countryProvider->id,
                        'country_id' => $countryId,
                        'provider_id' => $providerId,
                        'type' => $streamType,
                        'tmdb_link' => $tmdbLink,
                        'priority' => $raw['display_priority'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        return $rows;
    }

    private function persistTitleProviders(int $titleId, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        TitleProvider::where('title_id', $titleId)->delete();
        foreach (array_chunk($rows, 500) as $chunk) {
            TitleProvider::insert($chunk);
        }
    }
}
