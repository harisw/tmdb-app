<?php

namespace App\Jobs;

use App\Data\DTOs\TmdbApi\TmdbTitleData;
use App\Domain\Enums\TitleType;
use App\Models\Language;
use App\Models\Title;
use App\Services\TitleService;
use App\Services\TmdbService;
use App\Utils\Enum\TimeWindow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FetchTrendingTitlesJob implements ShouldQueue
{
    use Queueable;

    private TimeWindow $timeWindow;

    private TitleType $titleType;

    private ?Collection $titleIdDict = null;

    private int $maxPage;

    /**
     * Create a new job instance.
     */
    public function __construct(TimeWindow $timeWindow, TitleType $titleType, int $maxPage = 10)
    {
        $this->timeWindow = $timeWindow;
        $this->titleType = $titleType;
        $this->maxPage = $maxPage;
    }

    /**
     * Execute the job.
     */
    public function handle(TmdbService $tmdbService, TitleService $titleService): void
    {
        $this->titleIdDict = Title::where('type', $this->titleType->value)
            ->whereNotNull('tmdb_id')
            ->pluck('id', 'tmdb_id');

        $page = 0;
        do {
            $response = $tmdbService->getTrendings($this->titleType, $this->timeWindow, ++$page);
            $this->processPage($tmdbService, $titleService, $response['results']);
            sleep(1);
        } while ($page < $this->maxPage);
    }

    private function processPage(TmdbService $tmdbService, TitleService $titleService, ?array $results): void
    {
        foreach ($results ?? [] as $title) {
            $tmdbId = $title['id'];
            if ($this->titleIdDict->has($tmdbId)) {
                continue;
            }
            try {
                $titleDetail = $tmdbService->getTitle($this->titleType, $tmdbId);
                $newTitle = $this->createNewTitle($titleDetail, $titleService);
                if ($newTitle) {
                    $this->titleIdDict->put($tmdbId, $newTitle->id);
                }
                EnrichTitleJob::dispatchSync($newTitle);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }
    }

    private function createNewTitle(array $titleDetail, TitleService $titleService): ?Title
    {
        $languageId = Language::findByCode($titleDetail['original_language'])->id;

        $tmdbData = TmdbTitleData::from(array_merge($titleDetail, [
            'language_id' => $languageId,
            'type' => $this->titleType,
        ]));

        return $titleService->insertRow($tmdbData);
    }
}
