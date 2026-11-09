<?php

namespace App\Services;

use App\Data\DTOs\ProviderData;
use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Title;
use App\Models\TitleProvider;
use App\Services\ProviderServices\ProviderCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WatchProviderSyncService
{
    private Collection $countryMap;

    private Collection $countryProvidersMap;

    public function __construct(private readonly ProviderCatalog $catalog)
    {
        $this->countryMap = Country::pluck('id', 'iso2');
        $this->loadCountryProviders();
    }

    public function sync(Title $title, array $results): void
    {
        $entries = $this->normalizeEntries($results, StreamType::values());
        $this->linkMissingCountryProviders($entries);

        $rows = array_values($this->buildTitleProviderRows($title, $entries));
        $this->persistTitleProviders($title->id, $rows);
    }

    private function loadCountryProviders(): void
    {
        $this->countryProvidersMap = CountryProvider::select(['id', 'country_id', 'provider_id'])
            ->get()
            ->keyBy(fn ($cp) => "{$cp->country_id}-{$cp->provider_id}");
    }

    private function linkMissingCountryProviders(array $entries): void
    {
        $missingCountryProviders = $this->findMissingCountryProviders($entries);
        if (! $missingCountryProviders) {
            return;
        }

        CountryProvider::upsert(array_values($missingCountryProviders), ['country_id', 'provider_id'], ['priority']);
        $this->loadCountryProviders();
    }

    private function normalizeEntries(array $results, array $validStreamTypes): array
    {
        $entries = [];
        foreach ($results as $iso2 => $countryData) {
            $countryId = $this->countryMap->get($iso2);
            if (! $countryId) {
                Log::warning(self::class.": skipping unknown country [$iso2]");

                continue;
            }

            $link = $countryData['link'] ?? null;
            unset($countryData['link']);

            foreach ($countryData as $streamType => $providers) {
                if (! in_array($streamType, $validStreamTypes, true)) {
                    Log::warning(self::class.": skipping unknown stream type [{$streamType}]");

                    continue;
                }

                foreach (collect($providers)->unique('provider_id') as $raw) {
                    $entries[] = [
                        'country_id' => $countryId,
                        'provider_id' => $this->catalog->findOrCreateTmdbProvider(ProviderData::from($raw)),
                        'stream_type' => $streamType,
                        'link' => $link,
                        'raw' => $raw,
                    ];
                }
            }
        }

        return $entries;
    }

    private function findMissingCountryProviders(array $entries): array
    {
        $missing = [];

        foreach ($entries as $entry) {
            $providerId = $entry['provider_id'];

            $key = "{$entry['country_id']}-{$providerId}";
            if (! $this->countryProvidersMap->has($key) && ! isset($missing[$key])) {
                $missing[$key] = [
                    'country_id' => $entry['country_id'],
                    'provider_id' => $providerId,
                    'priority' => $entry['raw']['display_priority'],
                ];
            }
        }

        return $missing;
    }

    private function buildTitleProviderRows(Title $title, array $entries): array
    {
        $rows = [];
        $now = now();

        foreach ($entries as $entry) {
            $providerId = $entry['provider_id'];

            $countryProvider =
                $this->countryProvidersMap->get("{$entry['country_id']}-{$providerId}");
            if (! $countryProvider) {
                continue;
            }

            $rows["{$countryProvider->id}-{$entry['stream_type']}"] = [
                'title_id' => $title->id,
                'country_provider_id' => $countryProvider->id,
                'country_id' => $entry['country_id'],
                'provider_id' => $providerId,
                'type' => $entry['stream_type'],
                'tmdb_link' => $entry['link'],
                'priority' => $entry['raw']['display_priority'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    private function persistTitleProviders(int $titleId, array $rows): void
    {
        if (empty($rows)) {
            TitleProvider::where('title_id', $titleId)->whereNull('link')->delete();

            return;
        }

        TitleProvider::upsert(
            $rows,
            ['title_id', 'country_provider_id', 'type'],
            ['country_id', 'provider_id', 'tmdb_link', 'priority', 'updated_at']
        );

        $survivingIds = TitleProvider::where('title_id', $titleId)
            ->where(function ($q) use ($rows) {
                foreach ($rows as $row) {
                    $q->orWhere(fn ($inner) => $inner
                        ->where('country_provider_id', $row['country_provider_id'])
                        ->where('type', $row['type'])
                    );
                }
            })
            ->pluck('id');

        TitleProvider::where('title_id', $titleId)
            ->whereNull('link')
            ->whereNotIn('id', $survivingIds)
            ->delete();
    }
}
