<?php

namespace App\Services;

use App\Domain\Enums\TitleType;
use App\Utils\Enum\TimeWindow;
use Illuminate\Support\Facades\Http;

class TmdbService
{
    private Http $client;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {
        // This runs every single time with bind()
        $this->client = Http::withToken($apiKey)
            ->baseUrl($this->baseUrl)
            ->withHeader(['accept' => 'application/json'])
            ->timeout(30);
    }

    public function getTitle(TitleType $type, int $id)
    {
        return $this->client->get("3/{$type}/{$id}")
            ->json();
    }

    public function getTrendings(TitleType $type, TimeWindow $window)
    {
        return $this->client->get("3/trending/{$type}/{$window}")
            ->json();
    }
}
