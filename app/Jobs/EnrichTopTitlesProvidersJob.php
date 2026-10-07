<?php

namespace App\Jobs;

use App\Models\Country;
use App\Models\Provider;
use App\Services\ProviderServices\StreamingAvailabilityService;
use App\Services\ProviderServices\TitleProviderWriter;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class EnrichTopTitlesProvidersJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Provider slugs per country ISO2, ranked from most to least used.
     *
     * @var array<string, list<string>>
     */
    public const PROVIDER_SLUGS_BY_COUNTRY = [
        'KR' => ['netflix', 'disney-plus', 'apple-tv', 'crunchyroll'],
        'US' => ['netflix', 'amazon-prime-video', 'hulu', 'disney-plus', 'hbo-max', 'crunchyroll'],
        'ID' => ['netflix', 'disney-plus', 'amazon-prime-video', 'hbo-max', 'crunchyroll'],
        'IN' => ['amazon-prime-video', 'netflix', 'disney-plus', 'crunchyroll'],
        'AT' => ['netflix', 'amazon-prime-video', 'disney-plus', 'hbo-max', 'apple-tv'],
        'DE' => ['netflix', 'amazon-prime-video', 'disney-plus', 'hbo-max', 'apple-tv'],
        'GB' => ['netflix', 'amazon-prime-video', 'disney-plus', 'hbo-max', 'apple-tv'],
    ];

    public function __construct(private readonly Country $country)
    {
        $this->onQueue('enrichment');
    }

    public function handle(StreamingAvailabilityService $service, TitleProviderWriter $writer): void
    {
        $writer->upsert($this->providers()->flatMap(
            fn (Provider $provider) => $service->getTopShows($this->country, $provider)
        ));
    }

    /**
     * @return Collection<int, Provider>
     */
    private function providers(): Collection
    {
        $slugs = self::PROVIDER_SLUGS_BY_COUNTRY[$this->country->iso2] ?? [];

        return Provider::whereIn('slug', $slugs)->get()
            ->sortBy(fn (Provider $provider) => array_search($provider->slug, $slugs, true))
            ->values();
    }
}
