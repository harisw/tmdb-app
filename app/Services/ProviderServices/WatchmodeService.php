<?php

namespace App\Services\ProviderServices;

use App\Data\DTOs\TitleProviderData;
use App\Domain\Enums\ProviderSource;
use App\Domain\Enums\StreamQuality;
use App\Models\Title;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class WatchmodeService
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
            ->timeout(10)
            ->retry([500, 2000], when: fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false);
    }

    /**
     * @return Collection<int, TitleProviderData>
     */
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

    /**
     * @param  array<string, mixed>  $item
     */
    private function toDTO(array $item, Title $title): ?TitleProviderData
    {
        $providerId = $this->catalog->providerId(ProviderSource::WATCHMODE, $item['name']);
        $countryId = $this->catalog->countryId($item['region']);

        $countryProviderId = $providerId && $countryId
            ? $this->catalog->countryProviderId($countryId, $providerId)
            : null;
        if (! $countryProviderId) {
            return null;
        }

        return TitleProviderData::from([
            'type' => $item['type'],
            'country_id' => $countryId,
            'country_provider_id' => $countryProviderId,
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

        return "{$title->type->value}-{$title->tmdb_id}";
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function get(string $url): ?array
    {
        $response = $this->client->get($url);

        if ($response->notFound()) {
            return null;
        }

        return $response->throw()->json();
    }
}
