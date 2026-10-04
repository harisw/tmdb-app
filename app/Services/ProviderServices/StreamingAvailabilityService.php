<?php

namespace App\Services\ProviderServices;

use App\Data\DTOs\TitleProviderData;
use App\Domain\Enums\StreamQuality;
use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\Provider;
use App\Models\Title;
use Arr;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StreamingAvailabilityService
{
    private const APP_SLUG_BY_SERVICE_ID = [
        'netflix' => 'netflix',
        'prime' => 'amazon-prime-video',
        'disney' => 'disney-plus',
        'apple' => 'apple-tv',
        'hbo' => 'hbo-max',
        'crunchyroll' => 'crunchyroll',
        'crave' => 'crave',
        'hulu' => 'hulu',
        'roku' => 'the-roku-channel',
        'starz' => 'starz',
        'paramount' => 'paramount-plus',
        'peacock' => 'peacock-premium',
    ];

    private PendingRequest $client;

    private Country $country;

    public function __construct(
        string $apiKey,
        string $baseUrl,
        private ProviderCatalog $catalog,
    ) {
        $this->client = Http::baseUrl($baseUrl)
            ->withHeaders([
                'accept' => 'application/json',
                'X-API-Key' => $apiKey,
            ])
            ->timeout(30);
    }

    public function getTopShows(Country $country, Provider $provider): Collection
    {
        $this->country = $country;

        $shows = $this->get("shows/top?country={$country->iso2}&service={$this->getProviderKey($provider)}") ?? [];

        return collect($shows)->flatMap(function (array $show) {
            $title = $this->getTitle($show);
            if (! $title) {
                Log::warning('Title not found', $show);

                return [];
            }

            return $this->toDTOs($show['streamingOptions'] ?? [], $title);
        });
    }

    private function toDTOs(array $streamingOptions, Title $title): Collection
    {
        return collect($streamingOptions[strtolower($this->country->iso2)] ?? [])
            ->reject(fn ($option) => $option['type'] === StreamType::ADDON->value)
            ->map(fn ($option) => $this->toDTO($option, $title))
            ->filter()
            ->values();
    }

    private function toDTO(array $option, Title $title): ?TitleProviderData
    {
        $providerId = $this->catalog->providerId(self::APP_SLUG_BY_SERVICE_ID[$option['service']['id']] ?? null);
        if (! $providerId) {
            return null;
        }

        return TitleProviderData::from([
            'type' => $option['type'],
            'country_id' => $this->country->id,
            'provider_id' => $providerId,
            'title_id' => $title->id,
            'link' => $option['link'],
            'quality' => StreamQuality::mapString($option['quality'] ?? ''),
        ]);
    }

    private function getProviderKey(Provider $provider): string
    {
        return array_search($provider->slug, self::APP_SLUG_BY_SERVICE_ID, strict: true)
            ?: throw new \RuntimeException('Provider is not supported: '.$provider->toJson());
    }

    private function getTitle(array $showWithProviders): ?Title
    {
        $imdbId = Arr::get($showWithProviders, 'imdbId');
        [$type, $tmdbId] = explode('/', Arr::get($showWithProviders, 'tmdbId', ''));

        return Title::where('imdb_id', $imdbId)
            ->orWhere(fn ($q) => $q->where('type', $type)->where('tmdb_id', $tmdbId))
            ->first();
    }

    private function get(string $url): ?array
    {
        return $this->client->get($url)->json();
    }
}
