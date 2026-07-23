<?php

namespace App\Jobs;

use App\Domain\Enums\TitleType;
use App\Services\TmdbApiService;
use App\Utils\Enum\TimeWindow;

class FetchTrendingTitlesJob extends FetchTitlesJob
{
    public function __construct(
        private readonly TimeWindow $timeWindow,
        TitleType $titleType,
        int $limit = 100,
        int $maxPages = 100,
    ) {
        parent::__construct($titleType, $limit, $maxPages);
    }

    protected function fetchPage(TmdbApiService $tmdbService, int $page): ?array
    {
        return $tmdbService->getTrendings($this->titleType, $this->timeWindow, $page);
    }
}
