<?php

namespace App\Console\Commands;

use App\Domain\Enums\TitleType;
use App\Jobs\EnrichTitleCreditsBatchJob;
use App\Jobs\EnrichTitleKnowledgeGraphsJob;
use App\Jobs\EnrichTitleProvidersBatchJob;
use App\Jobs\FetchPopularTitlesJob;
use App\Jobs\FetchTitlesJob;
use App\Jobs\FetchTrendingTitlesJob;
use App\Jobs\FetchUpcomingTitlesJob;
use App\Jobs\TitlesBatchInsertJob;
use App\Utils\Enum\TimeWindow;
use Illuminate\Console\Command;
use Log;

class RunJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Job by command';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $jobClass = $this->choice('Which job class do you want to run?',
            [FetchTrendingTitlesJob::class, FetchPopularTitlesJob::class, FetchUpcomingTitlesJob::class,
                EnrichTitleProvidersBatchJob::class, TitlesBatchInsertJob::class,
                EnrichTitleCreditsBatchJob::class, EnrichTitleKnowledgeGraphsJob::class],
        );
        switch ($jobClass) {
            case FetchTrendingTitlesJob::class:
                $this->runFetchTrendingTitles();
                break;
            case FetchPopularTitlesJob::class:
            case FetchUpcomingTitlesJob::class:
                $this->runFetchTitles($jobClass);
                break;
            case EnrichTitleProvidersBatchJob::class:
                $this->runEnrichTitlesProvider();
                break;
            case TitlesBatchInsertJob::class:
                $this->runTitleBatchInsert();
                break;
            case EnrichTitleCreditsBatchJob::class:
                $this->runEnrichTitleCredits();
                break;
            case EnrichTitleKnowledgeGraphsJob::class:
                $this->runEnrichTitleKnowledgeGraphs();
                break;
            default:
                $this->error('Unknown job class');
        }
        $this->info('*** DONE ***');
    }

    private function runFetchTrendingTitles(): void
    {
        $timeWindow = TimeWindow::from($this->choice('Which time window?',
            TimeWindow::values()
        ));
        $titleType = TitleType::from($this->choice('Which type of title?',
            TitleType::values()
        ));
        $limit = (int) $this->ask('How many new titles to insert?');
        Log::channel('user')->info('Running run Job', [
            'timeWindow' => $timeWindow->value,
            'titleType' => $titleType->value,
            'limit' => $limit,
        ]);
        FetchTrendingTitlesJob::dispatch($timeWindow, $titleType, $limit);
    }

    /**
     * @param  class-string<FetchTitlesJob>  $jobClass
     */
    private function runFetchTitles(string $jobClass): void
    {
        $titleType = TitleType::from($this->choice('Which type of title?',
            TitleType::values()
        ));
        $limit = (int) $this->ask('How many new titles to insert?');
        Log::channel('user')->info('Running run Job', [
            'job' => $jobClass,
            'titleType' => $titleType->value,
            'limit' => $limit,
        ]);
        $jobClass::dispatch($titleType, $limit);
    }

    private function runEnrichTitlesProvider(): void
    {
        $limit = (int) $this->ask('Limit titles?');
        EnrichTitleProvidersBatchJob::dispatch($limit);
    }

    private function runTitleBatchInsert(): void
    {
        $batchSize = (int) $this->ask('Size of batch?', 10_000);
        TitlesBatchInsertJob::dispatch($batchSize);
    }

    private function runEnrichTitleCredits(): void
    {
        $limit = (int) $this->ask('Limit titles?', 10);
        EnrichTitleCreditsBatchJob::dispatch($limit);
    }

    private function runEnrichTitleKnowledgeGraphs(): void
    {
        $limit = (int) $this->ask('Limit titles?', 10);
        EnrichTitleKnowledgeGraphsJob::dispatch($limit);
    }
}
