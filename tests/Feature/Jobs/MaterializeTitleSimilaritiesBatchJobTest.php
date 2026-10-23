<?php

namespace Tests\Feature\Jobs;

use App\Jobs\MaterializeTitleSimilaritiesBatchJob;
use App\Jobs\MaterializeTitleSimilaritiesJob;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MaterializeTitleSimilaritiesBatchJobTest extends TestCase
{
    use RefreshDatabase;

    private const DIMENSIONS = 1536;

    private function embeddedTitle(array $attributes = []): Title
    {
        $title = Title::factory()->create($attributes);
        $title->updateQuietly(['embedding' => '['.implode(',', array_fill(0, self::DIMENSIONS, 0.1)).']']);

        return $title;
    }

    public function test_refreshes_titles_never_materialized_then_the_oldest_first(): void
    {
        Queue::fake();
        $this->embeddedTitle(['similarities_materialized_at' => now()->subWeeks(2)]);
        $never = $this->embeddedTitle();
        $oldest = $this->embeddedTitle(['similarities_materialized_at' => now()->subWeeks(3)]);
        Title::factory()->create();

        (new MaterializeTitleSimilaritiesBatchJob(limit: 2))->handle();

        $this->assertQueued([$never, $oldest]);
    }

    public function test_skips_titles_materialized_within_the_last_week(): void
    {
        Queue::fake();
        $stale = $this->embeddedTitle(['similarities_materialized_at' => now()->subDays(8)]);
        $this->embeddedTitle(['similarities_materialized_at' => now()->subDays(6)]);

        (new MaterializeTitleSimilaritiesBatchJob)->handle();

        $this->assertQueued([$stale]);
    }

    public function test_can_include_recently_materialized_titles(): void
    {
        Queue::fake();
        $fresh = $this->embeddedTitle(['similarities_materialized_at' => now()->subDay()]);

        (new MaterializeTitleSimilaritiesBatchJob(includeFresh: true))->handle();

        $this->assertQueued([$fresh]);
    }

    /**
     * @param  array<int, Title>  $titles
     */
    private function assertQueued(array $titles): void
    {
        Queue::assertPushed(MaterializeTitleSimilaritiesJob::class, count($titles));
        foreach ($titles as $title) {
            Queue::assertPushed(MaterializeTitleSimilaritiesJob::class, fn ($job) => $job->titleId === $title->id);
        }
    }
}
