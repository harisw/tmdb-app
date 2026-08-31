<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EnrichTitleCreditsBatchJob;
use App\Jobs\EnrichTitleCreditsJob;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnrichTitleCreditsBatchJobTest extends TestCase
{
    use RefreshDatabase;

    private function assertQueuedFor(Title $title): void
    {
        Queue::assertPushed(
            EnrichTitleCreditsJob::class,
            fn (EnrichTitleCreditsJob $job) => (new \ReflectionProperty($job, 'titleId'))->getValue($job) === $title->id,
        );
    }

    public function test_queues_one_job_per_title_needing_credits(): void
    {
        Queue::fake();

        // Stale must sit the far side of RETRY_MONTHS_THRESHOLD; fresh must not.
        $needsCredits = Title::factory()->create(['tmdb_id' => 1, 'credits_filled_at' => null, 'popularity' => 100]);
        $stale = Title::factory()->create(['tmdb_id' => 2, 'credits_filled_at' => now()->subMonths(7), 'popularity' => 50]);
        Title::factory()->create(['tmdb_id' => 3, 'credits_filled_at' => now()->subDay(), 'popularity' => 200]);
        // Boundary: inside the window, so not due for a re-fetch despite being old.
        Title::factory()->create(['tmdb_id' => 4, 'credits_filled_at' => now()->subMonths(5), 'popularity' => 300]);

        (new EnrichTitleCreditsBatchJob(10))->handle();

        Queue::assertPushed(EnrichTitleCreditsJob::class, 2);
        $this->assertQueuedFor($needsCredits);
        $this->assertQueuedFor($stale);
    }

    public function test_prefers_popular_titles_within_its_limit(): void
    {
        Queue::fake();

        Title::factory()->create(['tmdb_id' => 4, 'credits_filled_at' => null, 'popularity' => 1]);
        $popular = Title::factory()->create(['tmdb_id' => 5, 'credits_filled_at' => null, 'popularity' => 999]);

        (new EnrichTitleCreditsBatchJob(1))->handle();

        Queue::assertPushed(EnrichTitleCreditsJob::class, 1);
        $this->assertQueuedFor($popular);
    }
}
