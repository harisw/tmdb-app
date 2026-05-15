<?php

namespace App\Services;

use App\Domain\Enums\TitleType;
use App\Utils\Enum\TimeWindow;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TmdbService
{
    private PendingRequest $client;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {
        // This runs every single time with bind()
        $this->client = Http::withToken($apiKey)
            ->baseUrl($this->baseUrl)
            ->withHeader('accept', 'application/json')
            ->timeout(30);
    }

    public function getTitle(TitleType $type, int $id)
    {
        return $this->client->get("3/{$type->value}/{$id}")
            ->json();
    }

    public function getTrendings(TitleType $type, TimeWindow $window, int $page = 1)
    {
        return $this->client->get("3/trending/{$type->value}/{$window->value}",
            ['page' => $page])
            ->json();
    }

    public function getKeywords(TitleType $type, int $tmdbId)
    {
        return $this->client->get("3/{$type->value}/{$tmdbId}/keywords")
            ->json();
    }

    public function getWatchProviders(TitleType $type, int $tmdbId)
    {
        return $this->client->get("3/{$type->value}/{$tmdbId}/watch/providers")
            ->json();
    }
}
