<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Io238\ISOCountries\Models\Country as ISOCountry;

class CountrySeeder extends Seeder
{
    private Collection $languageMapping;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->languageMapping = Language::pluck('id', 'code');
        $data = json_decode(file_get_contents(__DIR__.'/data/countries.json'), true);

        $result = array_map(function ($item) {
            $mapping = $this->getProperties($item['iso2']);

            return [
                'name' => $item['english_name'],
                'iso2' => $item['iso2'],
                'iso3' => $mapping['iso3'],
                'language_id' => $mapping['language_id'],
            ];
        }, $data);

        foreach (array_chunk($result, 1000) as $chunkedData) {
            Country::upsert($chunkedData, ['iso2']);
        }

        Cache::forget('countries');
    }

    private function getProperties(string $iso2): array
    {
        $isoCountry = ISOCountry::find($iso2);
        $langCode = $isoCountry?->languages?->first()?->id;
        $languageId = $this->languageMapping->get($langCode) ?? Language::findOther()->id;

        return [
            'iso3' => $isoCountry?->alpha3,
            'language_id' => $languageId,
        ];
    }
}
