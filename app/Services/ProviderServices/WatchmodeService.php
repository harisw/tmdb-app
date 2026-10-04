<?php

namespace App\Services\ProviderServices;

use App\Data\DTOs\TitleProviderData;
use App\Domain\Enums\StreamQuality;
use App\Domain\Enums\TitleType;
use App\Models\Title;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class WatchmodeService
{
    private const APP_SLUG_BY_PROVIDER_NAME = [
        'Amazon' => 'amazon-video',
        'AppleTV' => 'apple-tv',
        'Fandango at Home' => 'fandango-at-home',
        'Rakuten TV' => 'rakuten-tv',
        'maxdome Store' => 'maxdome-store',
        'Sky Store' => 'sky-store',
        'Peacock' => 'peacock-premium',
        'Peacock Premium' => 'peacock-premium',
    ];

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

    public function getProviders(Title $title): Collection
    {
        if ($title->tmdb_id === null && $title->imdb_id === null) {
            return collect();
        }

        $raw = $this->get("title/{$this->getIdentifier($title)}/sources") ?? [];

        return collect($raw)
            ->unique(fn ($item) => $item['name'].$item['type'].$item['region'].$item['format'])
            ->map(fn ($item) => $this->toDTO($item, $title))
            ->filter()
            ->values();
    }

    private function toDTO(array $item, Title $title): ?TitleProviderData
    {
        $providerId = $this->catalog->providerId(self::APP_SLUG_BY_PROVIDER_NAME[$item['name']] ?? null);
        $countryId = $this->catalog->countryId($item['region']);

        if (! $providerId || ! $countryId) {
            return null;
        }

        return TitleProviderData::from([
            'type' => $item['type'],
            'country_id' => $countryId,
            'provider_id' => $providerId,
            'title_id' => $title->id,
            'link' => $item['web_url'],
            'quality' => StreamQuality::mapString($item['format'] ?? ''),
        ]);
    }

    private function getIdentifier(Title $title): string
    {
        if ($title->imdb_id) {
            return $title->imdb_id;
        }

        return match ($title->type) {
            TitleType::MOVIE => 'movie',
            TitleType::TV => 'tv',
        }."-{$title->tmdb_id}";
    }

    private function get(string $url): ?array
    {
        return $this->client->get($url)->json();
    }
}
