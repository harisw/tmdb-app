<?php

namespace App\Services;

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
            ->timeout(30);
    }
}
