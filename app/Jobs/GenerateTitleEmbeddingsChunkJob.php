<?php

namespace App\Jobs;

use App\Models\Title;
use App\Services\KnowledgeGraph\OpenAIEmbeddingService;
use App\Services\KnowledgeGraph\TitleDocumentBuilder;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateTitleEmbeddingsChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $backoff = 30;

    /**
     * @param  int[]  $titleIds
     */
    public function __construct(public readonly array $titleIds) {}

    public function handle(
        OpenAIEmbeddingService $embeddingService,
        TitleDocumentBuilder $documentBuilder
    ): void {
        if ($this->batch()?->canceled()) {
            return;
        }

        $titles = Title::with(['genres', 'keywords', 'people', 'collection'])
            ->whereIn('id', $this->titleIds)
            ->get()
            ->values(); // guarantee a 0-indexed order that matches the embeddings

        if ($titles->isEmpty()) {
            return;
        }

        $documents = $titles->map(fn (Title $title) => $documentBuilder->build($title))->all();

        // One HTTP request for the whole chunk; embedBatch re-sorts by response
        // index, so $vectors[$i] lines up with $titles[$i].
        $vectors = $embeddingService->embedBatch($documents);

        foreach ($titles as $i => $title) {
            // pgvector expects the vector as a string: '[0.1,0.2,...]'
            $title->updateQuietly([
                'embedding' => '['.implode(',', $vectors[$i]).']',
            ]);
        }
    }
}
