<?php

namespace App\Services\ProviderServices;

use App\Data\DTOs\TitleProviderData;
use App\Domain\Enums\ProviderSource;
use App\Domain\Enums\StreamQuality;
use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\Title;
use Arr;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StreamingAvailabilityService
{
    private PendingRequest $client;

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

    public function getTopShows(Country $country, string $serviceId): Collection
    {
        if (! $this->catalog->providerId(ProviderSource::STREAMING_AVAILABILITY, $serviceId)) {
            return collect();
        }

        $shows = $this->get("shows/top?country={$country->iso2}&service={$serviceId}") ?? [];

        return collect($shows)->flatMap(function (array $show) use ($country) {
            $title = $this->getTitle($show);
            if (! $title) {
                Log::warning('Title not found', $show);

                return [];
            }
            Log::info("Found {$title->slug}", $show);

            return $this->toDTOs($show['streamingOptions'] ?? [], $title, $country);
        });
    }

    private function toDTOs(array $streamingOptions, Title $title, Country $country): Collection
    {
        return collect($streamingOptions[strtolower($country->iso2)] ?? [])
            ->reject(fn ($option) => $option['type'] === StreamType::ADDON->value)
            ->map(fn ($option) => $this->toDTO($option, $title, $country))
            ->filter()
            ->values();
    }

    private function toDTO(array $option, Title $title, Country $country): ?TitleProviderData
    {
        $providerId = $this->catalog->providerId(ProviderSource::STREAMING_AVAILABILITY, $option['service']['id']);
        $countryProviderId = $providerId
            ? $this->catalog->countryProviderId($country->id, $providerId)
            : null;
        if (! $countryProviderId) {
            return null;
        }

        return TitleProviderData::from([
            'type' => $option['type'],
            'country_id' => $country->id,
            'country_provider_id' => $countryProviderId,
            'provider_id' => $providerId,
            'title_id' => $title->id,
            'link' => $option['link'],
            'quality' => StreamQuality::mapString($option['quality'] ?? ''),
        ]);
    }

    private function getTitle(array $showWithProviders): ?Title
    {
        $imdbId = Arr::get($showWithProviders, 'imdbId');
        [$type, $tmdbId] = array_pad(explode('/', Arr::get($showWithProviders, 'tmdbId', '')), 2, null);

        return Title::where('imdb_id', $imdbId)
            ->orWhere(fn ($q) => $q->where('type', $type)->where('tmdb_id', $tmdbId))
            ->first();
    }

    private function get(string $url): ?array
    {
        return $this->client->get($url)->throw()->json();
    }
}
