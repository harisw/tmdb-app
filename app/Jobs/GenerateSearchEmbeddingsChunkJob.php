<?php

namespace App\Jobs;

use App\Jobs\Middleware\MeasureDatabaseCost;
use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\KnowledgeGraph\OpenAIEmbeddingService;
use App\Services\Search\SearchDocumentBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class GenerateSearchEmbeddingsChunkJob implements ShouldQueue
{
    use Queueable;

    private const STATEMENT_TIMEOUT_SECONDS = 240;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  array<int, int>  $titleIds
     */
    public function __construct(public readonly array $titleIds)
    {
        $this->onQueue('embeddings');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new MeasureDatabaseCost(['title_search_embeddings'])];
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

        DB::transaction(function () use ($titles, $vectors): void {
            DB::statement('SET LOCAL statement_timeout = '.(self::STATEMENT_TIMEOUT_SECONDS * 1000));

            TitleSearchEmbedding::upsert(
                $titles->map(fn (Title $title, int $i) => [
                    'title_id' => $title->id,
                    'embedding' => TitleSearchEmbedding::toVectorLiteral($vectors[$i]),
                    'document_version' => SearchDocumentBuilder::VERSION,
                ])->all(),
                ['title_id'],
                ['embedding', 'document_version'],
            );
        });
    }
}
