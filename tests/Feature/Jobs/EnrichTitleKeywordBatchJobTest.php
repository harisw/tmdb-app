<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EnrichTitleJob;
use App\Jobs\EnrichTitleKeywordBatchJob;
use App\Models\Keyword;
use App\Models\Title;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class EnrichTitleKeywordBatchJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<int>
     */
    private function batchedTitleIds(PendingBatch $batch): array
    {
        return $batch->jobs
            ->map(fn (EnrichTitleJob $job) => (new \ReflectionProperty($job, 'title'))->getValue($job)->id)
            ->all();
    }

    public function test_batches_keywordless_titles_enriched_over_three_months_ago(): void
    {
        Bus::fake();

        $due = Title::factory()->create(['tmdb_id' => 1, 'enriched_at' => now()->subMonths(4)]);
        $hasKeywords = Title::factory()->create(['tmdb_id' => 2, 'enriched_at' => now()->subMonths(4)]);
        $hasKeywords->keywords()->attach(Keyword::factory()->create());
        Title::factory()->create(['tmdb_id' => 3, 'enriched_at' => now()->subMonths(2)]);
        Title::factory()->create(['tmdb_id' => 4, 'enriched_at' => null]);
        Title::factory()->create(['tmdb_id' => null, 'enriched_at' => now()->subMonths(4)]);

        (new EnrichTitleKeywordBatchJob(10))->handle();

        Bus::assertBatched(fn (PendingBatch $batch) => $this->batchedTitleIds($batch) === [$due->id]);
    }

    public function test_prefers_popular_titles_within_its_limit(): void
    {
        Bus::fake();

        Title::factory()->create(['tmdb_id' => 1, 'enriched_at' => now()->subMonths(4), 'popularity' => 1]);
        $popular = Title::factory()->create(['tmdb_id' => 2, 'enriched_at' => now()->subMonths(4), 'popularity' => 99]);

        (new EnrichTitleKeywordBatchJob(1))->handle();

        Bus::assertBatched(fn (PendingBatch $batch) => $this->batchedTitleIds($batch) === [$popular->id]);
    }

    public function test_dispatches_no_batch_when_nothing_needs_backfilling(): void
    {
        Bus::fake();

        Title::factory()->create(['tmdb_id' => 1, 'enriched_at' => now()->subDay()]);

        (new EnrichTitleKeywordBatchJob(10))->handle();

        Bus::assertNothingBatched();
    }
}
