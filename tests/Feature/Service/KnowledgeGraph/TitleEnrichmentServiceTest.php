<?php

namespace Tests\Feature\Service\KnowledgeGraph;

use App\Jobs\GenerateTitleEmbeddingJob;
use App\Models\Title;
use App\Services\KnowledgeGraph\TitleEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TitleEnrichmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_enqueues_an_embedding_batch_for_the_title(): void
    {
        $this->app->forgetInstance(TitleEnrichmentService::class);
        Bus::fake();
        $title = Title::factory()->create();

        app(TitleEnrichmentService::class)->enqueue($title);

        Bus::assertBatched(fn ($batch) => $batch->name === "embed-title-{$title->id}"
            && $batch->jobs->first() instanceof GenerateTitleEmbeddingJob);
    }
}
