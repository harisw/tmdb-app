<?php

namespace App\Services;

use App\Domain\Enums\TitleType;
use App\Utils\Enum\TimeWindow;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TmdbApiService
{
    private const EXPORTS_URL = 'https://files.tmdb.org/p/exports';

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

    /**
     * @return array<string, mixed>|null
     */
    public function getTitle(TitleType $type, int $id): ?array
    {
        return $this->get("3/{$type->value}/{$id}");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTrendings(TitleType $type, TimeWindow $window, int $page = 1): ?array
    {
        return $this->get("3/trending/{$type->value}/{$window->value}", ['page' => $page]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPopulars(TitleType $type, int $page = 1): ?array
    {
        return $this->get("3/{$type->value}/popular", ['page' => $page]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUpcomings(TitleType $type, int $page = 1): ?array
    {
        $path = "3/{$type->value}/".match ($type) {
            TitleType::MOVIE => 'upcoming',
            TitleType::TV => 'on_the_air'
        };

        return $this->get($path, ['page' => $page]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getKeywords(TitleType $type, int $tmdbId): ?array
    {
        return $this->get("3/{$type->value}/{$tmdbId}/keywords");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getWatchProviders(TitleType $type, int $tmdbId): ?array
    {
        return $this->get("3/{$type->value}/{$tmdbId}/watch/providers");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getWatchProviderList(TitleType $type): ?array
    {
        return $this->get("3/watch/providers/{$type->value}");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCredits(TitleType $type, int $tmdbId): ?array
    {
        $path = "3/{$type->value}/{$tmdbId}/".match ($type) {
            TitleType::MOVIE => 'credits',
            TitleType::TV => 'aggregate_credits'
        };

        return $this->get($path);
    }

    public function downloadDailyExport(TitleType $type, string $path): void
    {
        $file = match ($type) {
            TitleType::MOVIE => 'movie_ids',
            TitleType::TV => 'tv_series_ids',
        };

        Http::timeout(300)
            ->sink($path)
            ->get(self::EXPORTS_URL."/{$file}_".now('UTC')->format('m_d_Y').'.json.gz')
            ->throw();
    }

    /**
     * Single place where a TMDB call is made and decoded, so every endpoint
     * above stays a one-liner describing *what* it asks for.
     *
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $query = []): ?array
    {
        $response = $this->client->get($path, $query);

        if ($response->notFound()) {
            return null;
        }

        return $response->throw()->json();
    }
}
