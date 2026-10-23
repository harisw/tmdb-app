<?php

namespace App\Jobs;

use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\KnowledgeGraph\OpenAIEmbeddingService;
use App\Services\Search\SearchDocumentBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateSearchEmbeddingsChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array<int, int>  $titleIds
     */
    public function __construct(public readonly array $titleIds)
    {
        $this->onQueue('embeddings');
    }

    public function handle(
        OpenAIEmbeddingService $embeddingService,
        SearchDocumentBuilder $documentBuilder
    ): void {
        $titles = Title::withoutGlobalScopes()
            ->with(SearchDocumentBuilder::relations())
            ->whereIn('id', $this->titleIds)
            ->get()
            ->values();

        if ($titles->isEmpty()) {
            return;
        }

        $vectors = $embeddingService->embedBatch(
            $titles->map(fn (Title $title) => $documentBuilder->build($title))->all(),
            TitleSearchEmbedding::DIMENSIONS,
        );

        TitleSearchEmbedding::upsert(
            $titles->map(fn (Title $title, int $i) => [
                'title_id' => $title->id,
                'embedding' => TitleSearchEmbedding::toVectorLiteral($vectors[$i]),
                'document_version' => SearchDocumentBuilder::VERSION,
            ])->all(),
            ['title_id'],
            ['embedding', 'document_version'],
        );
    }
}
