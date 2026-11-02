<?php

namespace App\Jobs;

use App\Data\DTOs\ProviderData;
use App\Domain\Enums\TitleType;
use App\Services\ProviderServices\ProviderCatalogImporter;
use App\Services\TmdbApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class ImportTmdbProvidersJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('enrichment');
    }

    public function handle(TmdbApiService $tmdb, ProviderCatalogImporter $importer): void
    {
        $aliases = require database_path('seeders/data/provider_aliases.php');
        $providers = collect(TitleType::cases())
            ->flatMap(fn (TitleType $type) => $tmdb->getWatchProviderList($type)['results']
                ?? throw new RuntimeException("TMDB returned no {$type->value} watch providers"))
            ->map(fn (array $record) => ProviderData::from($record));

        $importer->import($providers, $aliases['tmdb_variants'], $aliases['external']);
    }
}
