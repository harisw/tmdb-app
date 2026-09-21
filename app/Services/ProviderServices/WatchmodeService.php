<?php

namespace App\Services\ProviderServices;

use App\Domain\Enums\StreamQuality;
use App\Domain\Enums\TitleType;
use App\Models\Country;
use App\Models\Provider;
use App\Models\Title;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Str;

class WatchmodeService
{
    private PendingRequest $client;

    public function __construct(
        string $apiKey,
        string $baseUrl,
    ) {
        $this->client = Http::baseUrl($baseUrl)
            ->withHeaders([
                'accept' => 'application/json',
                'X-API-Key' => $apiKey,
            ])
            ->timeout(30);
    }

    public function getProviders(Title $title): array
    {
        if ($title->tmdb_id === null && $title->imdb_id === null) {
            return [];
        }
        $providers = $this->get("title/{$this->getIdentifier($title)}/sources");

        return $this->toDTOCollections($providers, $title);
    }

    private function getIdentifier(Title $title): string
    {
        if ($title->imdb_id) {
            return $title->imdb_id;
        }

        return match ($title->type) {
            TitleType::MOVIE => 'movie',
            TitleType::TV => 'tv'
        }."-{$title->tmdb_id}";
    }

    private function get(string $url): ?array
    {
        return $this->client->get($url)->json();
    }

    private function toDTOCollections(?array $providers, Title $title): Collection
    {
        $providersDict = Provider::with('mainProvider')->get()->keyBy('slug');
        $countryDict = Country::pluck('id', 'iso2');

        return collect($providers)
            ->unique(fn ($item) => $item['name'].$item['type'].$item['region'].$item['format'])
            ->map(function ($item) use ($providersDict, $countryDict, $title) {
                $item['country_id'] = $countryDict->get($item['region']);
                $provider = $providersDict->get(Str::slug($item['name']));
                $item['provider_id'] = $provider->isMainProvider() ? $provider->id : $provider->mainProvider->id;
                $item['title_id'] = $title->id;
                $item['quality'] = StreamQuality::mapString($item['format']);

                return $item;
            });

    }
}
