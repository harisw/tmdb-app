<?php

namespace Database\Seeders;

use App\Data\DTOs\ProviderData;
use App\Services\ProviderServices\ProviderCatalogImporter;
use Illuminate\Database\Seeder;

class TmdbProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ProviderCatalogImporter $importer): void
    {
        $aliases = require __DIR__.'/data/provider_aliases.php';
        $providers = collect(['movie-providers.json', 'tv-providers.json', 'providers.json'])
            ->flatMap(fn (string $file) => json_decode(file_get_contents(__DIR__."/data/{$file}"), true)['results'])
            ->map(fn (array $record) => ProviderData::from($record));

        $importer->import($providers, $aliases['tmdb_variants'], $aliases['external']);
    }
}
