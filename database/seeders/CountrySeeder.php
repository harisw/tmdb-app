<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languageIds = Language::pluck('id', 'code');
        $otherLanguageId = Language::findOther()->id;
        $data = json_decode(file_get_contents(__DIR__.'/data/countries.json'), true, flags: JSON_THROW_ON_ERROR);

        $result = array_map(fn (array $item): array => [
            'name' => $item['english_name'],
            'iso2' => $item['iso2'],
            'iso3' => $item['iso3'],
            'language_id' => $languageIds->get($item['language']) ?? $otherLanguageId,
        ], $data);

        foreach (array_chunk($result, 1000) as $chunkedData) {
            Country::upsert($chunkedData, ['iso2']);
        }

        Cache::forget('countries');
    }
}
