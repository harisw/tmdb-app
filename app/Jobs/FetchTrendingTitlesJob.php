<?php

namespace App\Jobs;

use App\Domain\Enums\TitleType;
use App\Models\Title;
use App\Services\TmdbService;
use App\Utils\Enum\TimeWindow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

class FetchTrendingTitlesJob implements ShouldQueue
{
    use Queueable;
    private TimeWindow $timeWindow;
    private TitleType $titleType;
    private Collection $titleIdDict;
    /**
     * Create a new job instance.
     */
    public function __construct(TimeWindow $timeWindow, TitleType $titleType)
    {
        $this->timeWindow = $timeWindow;
        $this->titleType = $titleType;
        $this->titleIdDict = Title::where('type', $titleType->value)
            ->whereNotNull('tmdb_id')
            ->pluck('id', 'tmdb_id')
        ->toArray();
    }

    /**
     * Execute the job.
     */
    public function handle(TmdbService $service): void
    {
        $trendingTitles = $service->getTrending($this->titleType, $this->timeWindow);

        foreach ($trendingTitles as $title) {
            $tmdbId = $title->id;
            if($this->titleIdDict->has($tmdbId)) {
                continue;
            }

            
        }
    }
}
