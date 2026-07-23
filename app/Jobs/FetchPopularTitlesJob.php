<?php

namespace App\Jobs;

use App\Services\TmdbApiService;

class FetchPopularTitlesJob extends FetchTitlesJob
{
    protected function fetchPage(TmdbApiService $tmdbService, int $page): ?array
    {
        return $tmdbService->getPopulars($this->titleType, $page);
    }
}
