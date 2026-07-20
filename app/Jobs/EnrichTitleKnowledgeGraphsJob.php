<?php

namespace App\Jobs;

use App\Models\Title;
use Bus;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class EnrichTitleKnowledgeGraphsJob implements ShouldQueue
{
    use Queueable;

    /**
     * How many titles each embedding request covers. Matches
     * OpenAIEmbeddingService::MAX_BATCH.
     */
    private const CHUNK_SIZE = 100;

    public function __construct(private readonly int $limit = 50) {}

    public function handle(): void
    {
        $titleIds = Title::whereNull('embedding')
            ->limit($this->limit)
            ->pluck('id');

        if ($titleIds->isEmpty()) {
            // Nothing to embed; skip so we don't dispatch an empty batch that
            // would re-materialise similarities for no reason.
            return;
        }

        // Embed in chunks so each job is a single OpenAI request instead of
        // one request per title.
        $jobs = $titleIds
            ->chunk(self::CHUNK_SIZE)
            ->map(fn ($chunk) => new GenerateTitleEmbeddingsChunkJob($chunk->values()->all()))
            ->all();

        Bus::batch($jobs)
            ->then(function (Batch $batch) use ($titleIds) {
                // Only materialise the titles embedded in this run. Reverse edges
                // (see MaterializeTitleSimilaritiesJob) keep existing titles linked
                // to these new ones without re-materialising the whole catalogue.
                $titleIds->each(fn ($id) => dispatch(new MaterializeTitleSimilaritiesJob($id)));
            })
            ->catch(fn (Batch $batch, Throwable $e) => \Log::error('Bulk enrich failed', ['error' => $e->getMessage()]))
            ->onQueue('embeddings')
            ->name('enrich-titles-'.now()->format('Y-m-d-H-i'))
            ->allowFailures()
            ->dispatch();
    }
}
