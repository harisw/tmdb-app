<?php

namespace App\Services\KnowledgeGraph;

use App\Jobs\GenerateTitleEmbeddingJob;
use App\Jobs\MaterializeTitleSimilaritiesJob;
use App\Models\Title;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class TitleEnrichmentService
{
    public function enqueue(Title $title): void
    {
        Bus::batch([new GenerateTitleEmbeddingJob($title->id)])
            ->then(fn (Batch $b) => dispatch(new MaterializeTitleSimilaritiesJob($title->id)))
            ->catch(fn (Batch $b, \Throwable $e) => \Log::error("Enrich failed for title {$title->id}",
                ['error' => $e->getMessage()]))
            ->onQueue('embeddings')
            ->name("embed-title-{$title->id}")
            ->allowFailures()
            ->dispatch();
    }
}
