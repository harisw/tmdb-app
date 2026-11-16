<?php

namespace App\Jobs;

use App\Services\TmdbApiService;

class FetchPopularTitlesJob extends FetchTitlesJob
{
    /**
     * @return array<string, mixed>|null
     */
    protected function fetchPage(TmdbApiService $tmdbService, int $page): ?array
    {
        return $tmdbService->getPopulars($this->titleType, $page);
    }
}
