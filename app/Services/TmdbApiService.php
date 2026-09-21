<?php

namespace App\Services;

use App\Domain\Enums\TitleType;
use App\Utils\Enum\TimeWindow;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TmdbApiService
{
    private PendingRequest $client;

    public function __construct(
        string $apiKey,
        string $baseUrl,
    ) {
        $this->client = Http::withToken($apiKey)
            ->baseUrl($baseUrl)
            ->withHeader('accept', 'application/json')
            ->timeout(30);
    }

    public function getTitle(TitleType $type, int $id): ?array
    {
        return $this->get("3/{$type->value}/{$id}");
    }

    public function getTrendings(TitleType $type, TimeWindow $window, int $page = 1): ?array
    {
        return $this->get("3/trending/{$type->value}/{$window->value}", ['page' => $page]);
    }

    public function getPopulars(TitleType $type, int $page = 1): ?array
    {
        return $this->get("3/{$type->value}/popular", ['page' => $page]);
    }

    public function getUpcomings(TitleType $type, int $page = 1): ?array
    {
        $path = "3/{$type->value}/".match ($type) {
            TitleType::MOVIE => 'upcoming',
            TitleType::TV => 'on_the_air'
        };

        return $this->get($path, ['page' => $page]);
    }

    public function getKeywords(TitleType $type, int $tmdbId): ?array
    {
        return $this->get("3/{$type->value}/{$tmdbId}/keywords");
    }

    public function getWatchProviders(TitleType $type, int $tmdbId): ?array
    {
        return $this->get("3/{$type->value}/{$tmdbId}/watch/providers");
    }

    public function getCredits(TitleType $type, int $tmdbId): ?array
    {
        $path = "3/{$type->value}/{$tmdbId}/".match ($type) {
            TitleType::MOVIE => 'credits',
            TitleType::TV => 'aggregate_credits'
        };

        return $this->get($path);
    }

    /**
     * Single place where a TMDB call is made and decoded, so every endpoint
     * above stays a one-liner describing *what* it asks for.
     */
    private function get(string $path, array $query = []): ?array
    {
        return $this->client->get($path, $query)->json();
    }
}
