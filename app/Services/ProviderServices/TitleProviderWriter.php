<?php

namespace App\Services\ProviderServices;

use App\Data\DTOs\TitleProviderData;
use App\Models\TitleProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TitleProviderWriter
{
    /**
     * @param  Collection<int, TitleProviderData>  $dtos
     */
    public function upsert(Collection $dtos): void
    {
        $now = now();
        Log::info("Received {$dtos->count()} DTOs");

        $dtos
            ->map(fn (TitleProviderData $dto) => [
                ...$dto->toUpsertArray(),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->unique(fn (array $row) => "{$row['title_id']}-{$row['country_provider_id']}-{$row['type']}")
            ->chunk(1000)
            ->each(fn (Collection $chunk) => TitleProvider::upsert(
                $chunk->values()->all(),
                ['title_id', 'country_provider_id', 'type'],
                ['country_id', 'provider_id', 'link', 'extra_attributes', 'updated_at'],
            ));
    }
}
