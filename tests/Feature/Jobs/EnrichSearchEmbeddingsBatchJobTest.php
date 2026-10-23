<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EnrichSearchEmbeddingsBatchJob;
use App\Jobs\GenerateSearchEmbeddingsChunkJob;
use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\Search\SearchDocumentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnrichSearchEmbeddingsBatchJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_missing_and_stale_titles_by_popularity(): void
    {
        Queue::fake();
        $missing = Title::factory()->create(['popularity' => 10]);
        $stale = Title::factory()->create(['popularity' => 20]);
        TitleSearchEmbedding::factory()->for($stale)->create(['document_version' => SearchDocumentBuilder::VERSION - 1]);
        TitleSearchEmbedding::factory()->create();

        (new EnrichSearchEmbeddingsBatchJob(10))->handle();

        Queue::assertPushed(GenerateSearchEmbeddingsChunkJob::class, 1);
        Queue::assertPushed(GenerateSearchEmbeddingsChunkJob::class,
            fn (GenerateSearchEmbeddingsChunkJob $job) => $job->titleIds === [$stale->id, $missing->id]);
    }

    public function test_splits_titles_into_chunks_of_one_hundred(): void
    {
        Queue::fake();
        Title::factory(150)->create();

        (new EnrichSearchEmbeddingsBatchJob(120))->handle();

        Queue::assertPushed(GenerateSearchEmbeddingsChunkJob::class, 2);
        Queue::assertPushed(GenerateSearchEmbeddingsChunkJob::class, fn ($job) => count($job->titleIds) === 100);
        Queue::assertPushed(GenerateSearchEmbeddingsChunkJob::class, fn ($job) => count($job->titleIds) === 20);
    }

    public function test_queues_nothing_when_every_title_is_current(): void
    {
        Queue::fake();
        TitleSearchEmbedding::factory()->create();

        (new EnrichSearchEmbeddingsBatchJob(10))->handle();

        Queue::assertNothingPushed();
    }
}
