<?php

namespace App\Jobs;

use App\Services\TmdbApiService;

class FetchUpcomingTitlesJob extends FetchTitlesJob
{
    protected function fetchPage(TmdbApiService $tmdbService, int $page): ?array
    {
        return $tmdbService->getUpcomings($this->titleType, $page);
    }
}
