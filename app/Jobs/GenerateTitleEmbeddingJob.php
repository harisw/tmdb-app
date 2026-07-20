<?php

namespace App\Jobs;

use App\Models\Title;
use App\Services\KnowledgeGraph\OpenAIEmbeddingService;
use App\Services\KnowledgeGraph\TitleDocumentBuilder;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateTitleEmbeddingJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $backoff = 30;

    public function __construct(public readonly int $titleId) {}

    public function handle(
        OpenAIEmbeddingService $embeddingService,
        TitleDocumentBuilder $documentBuilder
    ): void {
        if ($this->batch()?->canceled()) {
            return;
        }

        $title = Title::with(['genres', 'keywords', 'people', 'collection'])
            ->findOrFail($this->titleId);

        $document = $documentBuilder->build($title);
        $vector = $embeddingService->embedOne($document);

        // pgvector expects the vector as a string: '[0.1,0.2,...]'
        $title->updateQuietly([
            'embedding' => '['.implode(',', $vector).']',
        ]);
    }
}
