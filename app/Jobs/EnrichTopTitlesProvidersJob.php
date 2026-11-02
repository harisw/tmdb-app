<?php

namespace App\Jobs;

use App\Models\Country;
use App\Services\ProviderServices\StreamingAvailabilityService;
use App\Services\ProviderServices\TitleProviderWriter;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichTopTitlesProvidersJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Streaming Availability service ids per country ISO2, ranked from most to least used.
     *
     * @var array<string, list<string>>
     */
    public const SERVICE_IDS_BY_COUNTRY = [
        'KR' => ['netflix', 'disney', 'apple', 'crunchyroll'],
        'US' => ['netflix', 'prime', 'hulu', 'disney', 'hbo', 'crunchyroll'],
        'ID' => ['netflix', 'disney', 'prime', 'hbo', 'crunchyroll'],
        'IN' => ['prime', 'netflix', 'disney', 'crunchyroll'],
        'AT' => ['netflix', 'prime', 'disney', 'hbo', 'apple'],
        'DE' => ['netflix', 'prime', 'disney', 'hbo', 'apple'],
        'GB' => ['netflix', 'prime', 'disney', 'hbo', 'apple'],
    ];

    public function __construct(private readonly Country $country)
    {
        $this->onQueue('enrichment');
    }

    public function handle(StreamingAvailabilityService $service, TitleProviderWriter $writer): void
    {
        $writer->upsert(collect(self::SERVICE_IDS_BY_COUNTRY[$this->country->iso2] ?? [])->flatMap(
            fn (string $serviceId) => $service->getTopShows($this->country, $serviceId)
        ));
    }
}
