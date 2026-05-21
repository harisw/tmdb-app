<?php

namespace App\Services;

use App\Data\DTOs\ProviderData;
use App\Data\DTOs\ProviderDataCollection;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use Illuminate\Support\Collection;
use Str;

class CountryProviderService
{
    private Collection $countryDict;

    protected function __construct()
    {
        $this->countryDict = Country::pluck('id', 'iso2');
    }

    public static function upsertBulk(array $data): void
    {
        $collection = ProviderData::collect($data, ProviderDataCollection::class);
        Provider::upsert($collection->rows(), $collection->uniqueBy(), $collection->updateColumns());

        $service = new self;
        $providersDict = Provider::pluck('id', 'slug');

        $rows = [];
        foreach ($data as $item) {
            $providerId = $providersDict->get(Str::slug($item['provider_name']));
            if (! $providerId) {
                continue;
            }
            foreach ($item['display_priorities'] as $iso2 => $priority) {
                $countryId = $service->countryDict->get($iso2);
                if (! $countryId) {
                    continue;
                }

                $rows[] = [
                    'provider_id' => $providerId,
                    'country_id' => $countryId,
                    'priority' => $priority,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            CountryProvider::upsert($chunk, ['provider_id', 'country_id'], ['priority']);
        }
    }
}
