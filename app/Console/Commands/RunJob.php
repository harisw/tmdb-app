<?php

namespace App\Console\Commands;

use App\Domain\Enums\TitleType;
use App\Jobs\EnrichTitlesProviderBatchJob;
use App\Jobs\FetchTrendingTitlesJob;
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
            [FetchTrendingTitlesJob::class, EnrichTitlesProviderBatchJob::class],
        );
        switch ($jobClass) {
            case FetchTrendingTitlesJob::class:
                $this->runFetchTrendingTitles();
                break;
            case EnrichTitlesProviderBatchJob::class:
                $this->runEnrichTitlesProvider();
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
        $maxPage = (int) $this->ask('Max page number?');
        Log::channel('user')->info('Running run Job', [
            'timeWindow' => $timeWindow->value,
            'titleType' => $titleType->value,
            'maxPage' => $maxPage,
        ]);
        FetchTrendingTitlesJob::dispatch($timeWindow, $titleType, $maxPage);
    }

    private function runEnrichTitlesProvider(): void
    {
        $limit = (int) $this->ask('Limit titles?');
        EnrichTitlesProviderBatchJob::dispatch($limit);
    }
}
