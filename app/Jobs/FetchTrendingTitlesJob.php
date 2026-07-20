<?php

namespace App\Jobs;

use App\Data\DTOs\TmdbApi\TmdbTitleData;
use App\Domain\Enums\TitleType;
use App\Models\Language;
use App\Models\Title;
use App\Services\TitleService;
use App\Services\TmdbApiService;
use App\Utils\Enum\TimeWindow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FetchTrendingTitlesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    private TimeWindow $timeWindow;

    private TitleType $titleType;

    private ?Collection $titleIdDict = null;

    private int $limit;

    private int $maxPages;

    /**
     * Create a new job instance.
     *
     * @param  int  $limit  Number of *new* titles to insert. The job pages
     *                      through the trending feed until it has inserted this
     *                      many titles or TMDB runs out of pages, so it can
     *                      reach further into the feed as early pages fill up
     *                      with titles we already have.
     * @param  int  $maxPages  Safety bound on how many pages to walk in a single
     *                         run, so a quota that can't be met (e.g. a nearly
     *                         complete catalogue) can't make the job page all the
     *                         way to TMDB's 500-page cap within its timeout.
     */
    public function __construct(TimeWindow $timeWindow, TitleType $titleType, int $limit = 100, int $maxPages = 100)
    {
        $this->timeWindow = $timeWindow;
        $this->titleType = $titleType;
        $this->limit = $limit;
        $this->maxPages = $maxPages;
    }

    /**
     * Execute the job.
     */
    public function handle(TmdbApiService $tmdbService, TitleService $titleService): void
    {
        $this->titleIdDict = Title::where('type', $this->titleType->value)
            ->whereNotNull('tmdb_id')
            ->pluck('id', 'tmdb_id');

        $inserted = 0;
        $page = 0;
        do {
            $response = $tmdbService->getTrendings($this->titleType, $this->timeWindow, ++$page);
            $inserted += $this->processPage(
                $tmdbService,
                $titleService,
                $response['results'],
                $this->limit - $inserted,
            );
            sleep(1);
        } while ($inserted < $this->limit && $page < $response['total_pages'] && $page < $this->maxPages);
    }

    /**
     * @param  int  $remaining  How many more titles we're still allowed to insert.
     * @return int Number of new titles inserted from this page.
     */
    private function processPage(TmdbApiService $tmdbService, TitleService $titleService, ?array $results, int $remaining): int
    {
        $inserted = 0;
        foreach ($results ?? [] as $title) {
            if ($inserted >= $remaining) {
                break;
            }
            $tmdbId = $title['id'];
            if ($this->titleIdDict->has($tmdbId)) {
                continue;
            }
            try {
                $titleDetail = $tmdbService->getTitle($this->titleType, $tmdbId);
                $newTitle = $this->createNewTitle($titleDetail, $titleService);
                if ($newTitle) {
                    $this->titleIdDict->put($tmdbId, $newTitle->id);
                    EnrichTitleJob::dispatch($newTitle);
                    $inserted++;
                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }

        return $inserted;
    }

    private function createNewTitle(array $titleDetail, TitleService $titleService): ?Title
    {
        $languageId = Language::findByCode($titleDetail['original_language'])->id;

        $tmdbData = TmdbTitleData::from(array_merge($titleDetail, [
            'language_id' => $languageId,
            'type' => $this->titleType,
            ...($this->titleType === TitleType::TV
                ? [
                    'extra_attributes' => [
                        'number_of_episodes' => $titleDetail['number_of_episodes'] ?? 0,
                        'number_of_seasons' => $titleDetail['number_of_seasons'] ?? 0,
                    ],
                ]
                : []
            ),
        ]));

        return $titleService->insertRow($tmdbData);
    }
}
