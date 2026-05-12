<?php

namespace App\Jobs;

use App\Domain\Enums\TitleType;
use App\DTOs\TmdbApi\TmdbTitleData;
use App\Models\Language;
use App\Models\Title;
use App\Services\TitleService;
use App\Services\TmdbService;
use App\Utils\Enum\TimeWindow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

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
            ->pluck('id', 'tmdb_id');
    }

    /**
     * Execute the job.
     */
    public function handle(TmdbService $tmdbService, TitleService $titleService): void
    {
        $response = $tmdbService->getTrendings($this->titleType, $this->timeWindow);

        foreach ($response['results'] ?? [] as $title) {
            $tmdbId = $title['id'];
            if ($this->titleIdDict->has($tmdbId)) {
                continue;
            }

            try {
                $titleDetail = $tmdbService->getTitle($this->titleType, $tmdbId);
                $this->createNewTitle($titleDetail, $titleService);
            } catch (\Exception $e) {
                \Log::error($e->getMessage());
            }
        }
    }

    private function createNewTitle(array $titleDetail, TitleService $titleService): void
    {
        $languageId = Language::findByCode($titleDetail['original_language'])->id;

        $tmdbData = TmdbTitleData::from(array_merge($titleDetail, [
            'language_id' => $languageId,
            'type' => $this->titleType,
        ]));

        $titleService->insertRow($tmdbData);
    }
}
