<?php

namespace App\Jobs;

use App\Services\TmdbApiService;

class FetchUpcomingTitlesJob extends FetchTitlesJob
{
    /**
     * @return array<string, mixed>|null
     */
    protected function fetchPage(TmdbApiService $tmdbService, int $page): ?array
    {
        return $tmdbService->getUpcomings($this->titleType, $page);
    }
}
