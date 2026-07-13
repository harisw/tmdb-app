<?php

namespace Tests\Feature\Jobs;

use App\Domain\Enums\TitleType;
use App\Jobs\FetchTitleCreditsJob;
use App\Jobs\FetchTitlesCreditsJob;
use App\Models\Title;
use App\Services\TmdbApiService;
use App\Services\TmdbCredits\TmdbCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class FetchTitleCreditsJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(Title $title): void
    {
        (new FetchTitleCreditsJob($title->id))->handle(
            app(TmdbApiService::class),
            app(TmdbCreditService::class),
        );
    }

    public function test_imports_credits_and_stamps_the_title(): void
    {
        $title = Title::factory()->create([
            'type' => TitleType::MOVIE->value,
            'tmdb_id' => 550,
            'credits_filled_at' => null,
        ]);

        $this->mock(TmdbApiService::class)
            ->shouldReceive('getCredits')
            ->once()
            ->andReturn([
                'cast' => [[
                    'id' => 1, 'name' => 'Edward Norton', 'gender' => 2, 'adult' => false,
                    'popularity' => 20.0, 'profile_path' => '/e.jpg',
                    'character' => 'The Narrator', 'order' => 0, 'credit_id' => 'c1',
                ]],
                'crew' => [],
            ]);

        $this->runJob($title);

        $this->assertDatabaseHas('title_person', [
            'title_id' => $title->id,
            'character' => 'The Narrator',
        ]);
        $this->assertNotNull($title->fresh()->credits_filled_at);
    }

    public function test_stamps_the_title_even_when_tmdb_returns_no_credits(): void
    {
        // Otherwise a title TMDB has nothing for is re-queued on every run.
        $title = Title::factory()->create(['tmdb_id' => 550, 'credits_filled_at' => null]);

        $this->mock(TmdbApiService::class)
            ->shouldReceive('getCredits')
            ->andReturn(['cast' => [], 'crew' => []]);

        $this->runJob($title);

        $this->assertDatabaseCount('title_person', 0);
        $this->assertNotNull($title->fresh()->credits_filled_at);
    }

    public function test_leaves_the_title_unstamped_when_tmdb_fails_so_the_job_retries(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 550, 'credits_filled_at' => null]);

        $this->mock(TmdbApiService::class)
            ->shouldReceive('getCredits')
            ->andThrow(new RuntimeException('TMDB unavailable'));

        $this->expectException(RuntimeException::class);

        try {
            $this->runJob($title);
        } finally {
            $this->assertNull($title->fresh()->credits_filled_at);
        }
    }

    public function test_dispatcher_queues_one_job_per_title_needing_credits(): void
    {
        Queue::fake();

        $needsCredits = Title::factory()->create(['tmdb_id' => 1, 'credits_filled_at' => null, 'popularity' => 100]);
        $stale = Title::factory()->create(['tmdb_id' => 2, 'credits_filled_at' => now()->subMonths(4), 'popularity' => 50]);
        Title::factory()->create(['tmdb_id' => 3, 'credits_filled_at' => now()->subDay(), 'popularity' => 200]);

        (new FetchTitlesCreditsJob(10))->handle();

        Queue::assertPushed(FetchTitleCreditsJob::class, 2);
        foreach ([$needsCredits, $stale] as $title) {
            Queue::assertPushed(
                FetchTitleCreditsJob::class,
                fn (FetchTitleCreditsJob $job) => (new \ReflectionProperty($job, 'titleId'))->getValue($job) === $title->id,
            );
        }
    }

    public function test_dispatcher_skips_titles_that_have_no_tmdb_id(): void
    {
        Queue::fake();

        // CSV-imported titles can have no tmdb_id. There is nothing to fetch
        // against, and dispatching one only burns the retry budget.
        Title::factory()->create(['tmdb_id' => null, 'credits_filled_at' => null, 'popularity' => 500]);

        (new FetchTitlesCreditsJob(10))->handle();

        Queue::assertNothingPushed();
    }

    public function test_dispatcher_prefers_popular_titles_within_its_limit(): void
    {
        Queue::fake();

        Title::factory()->create(['tmdb_id' => 4, 'credits_filled_at' => null, 'popularity' => 1]);
        $popular = Title::factory()->create(['tmdb_id' => 5, 'credits_filled_at' => null, 'popularity' => 999]);

        (new FetchTitlesCreditsJob(1))->handle();

        Queue::assertPushed(FetchTitleCreditsJob::class, 1);
        Queue::assertPushed(
            FetchTitleCreditsJob::class,
            fn (FetchTitleCreditsJob $job) => (new \ReflectionProperty($job, 'titleId'))->getValue($job) === $popular->id,
        );
    }
}
