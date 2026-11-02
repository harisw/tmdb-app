<?php

namespace Tests\Feature\Console;

use App\Jobs\EnrichSearchEmbeddingsBatchJob;
use App\Jobs\EnrichTitleCreditsBatchJob;
use App\Jobs\EnrichTitleEmbeddingsBatchJob;
use App\Jobs\EnrichTitleProvidersBatchJob;
use App\Jobs\EnrichTitleProvidersLinkBatchJob;
use App\Jobs\EnrichTopTitlesProvidersBatchJob;
use App\Jobs\FetchPopularTitlesJob;
use App\Jobs\FetchTrendingTitlesJob;
use App\Jobs\FetchUpcomingTitlesJob;
use App\Jobs\MaterializeTitleSimilaritiesBatchJob;
use App\Jobs\TitlesBatchInsertJob;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunJobTest extends TestCase
{
    public function test_dispatches_the_chosen_job_and_logs_it_on_the_default_channel(): void
    {
        Queue::fake();
        Log::spy();

        $this->artisan('run:job')
            ->expectsChoice('Which job class do you want to run?', FetchPopularTitlesJob::class, $this->jobChoices())
            ->expectsChoice('Which type of title?', 'movie', ['movie', 'tv'])
            ->expectsQuestion('How many new titles to insert?', '5')
            ->assertSuccessful();

        Queue::assertPushed(FetchPopularTitlesJob::class);
        Log::shouldHaveReceived('info')->once()->with('Running run Job', [
            'job' => FetchPopularTitlesJob::class,
            'titleType' => 'movie',
            'limit' => 5,
        ]);
        Log::shouldNotHaveReceived('channel');
    }

    public function test_tags_logs_from_dispatched_jobs_with_the_run_job_trigger(): void
    {
        Queue::fake();

        $this->artisan('run:job')
            ->expectsChoice('Which job class do you want to run?', FetchPopularTitlesJob::class, $this->jobChoices())
            ->expectsChoice('Which type of title?', 'tv', ['movie', 'tv'])
            ->expectsQuestion('How many new titles to insert?', '5')
            ->assertSuccessful();

        $this->assertSame('run:job', Context::get('trigger'));
    }

    /**
     * @return array<int, string>
     */
    private function jobChoices(): array
    {
        return [
            FetchTrendingTitlesJob::class, FetchPopularTitlesJob::class, FetchUpcomingTitlesJob::class,
            EnrichTitleProvidersBatchJob::class, TitlesBatchInsertJob::class,
            EnrichTitleCreditsBatchJob::class, EnrichTitleEmbeddingsBatchJob::class,
            MaterializeTitleSimilaritiesBatchJob::class, EnrichTitleProvidersLinkBatchJob::class,
            EnrichTopTitlesProvidersBatchJob::class, EnrichSearchEmbeddingsBatchJob::class,
        ];
    }
}
