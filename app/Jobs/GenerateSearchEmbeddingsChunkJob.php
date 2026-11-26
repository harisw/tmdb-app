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
use RuntimeException;

class GenerateSearchEmbeddingsChunkJob implements ShouldQueue
{
    use Queueable;

    private const STATEMENT_TIMEOUT_SECONDS = 240;

    private const DEADLINE_MARGIN_SECONDS = 10;

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
        // When Horizon kills a timed-out worker, Postgres keeps running its query. Orphaned HNSW
        // upserts drained the t3.micro's EBS burst balance on 2026-11-16 (PR #95), so the
        // upsert must be cancelled by Postgres before the worker's own deadline.
        $deadline = now()->addSeconds($this->timeout - self::DEADLINE_MARGIN_SECONDS);

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

        // Loading and the OpenAI call (up to 60s) eat into the job's time before the upsert starts,
        // so cap the statement timeout to what is left rather than a fixed value.
        $remainingSeconds = (int) now()->diffInSeconds($deadline, false);

        if ($remainingSeconds <= 0) {
            $this->fail(new RuntimeException('No job time left to upsert search embeddings'));

            return;
        }

        DB::transaction(function () use ($titles, $vectors, $remainingSeconds): void {
            // is_local = true scopes the timeout to this transaction, like SET LOCAL.
            DB::select("SELECT set_config('statement_timeout', ?, true)", [
                (min(self::STATEMENT_TIMEOUT_SECONDS, $remainingSeconds) * 1000).'ms',
            ]);

            TitleSearchEmbedding::upsert(
                $titles->map(fn (Title $title, int $i) => [
                    'title_id' => $title->id,
                    'embedding' => OpenAIEmbeddingService::vectorArrayToLiteral($vectors[$i]),
                    'document_version' => SearchDocumentBuilder::VERSION,
                ])->all(),
                ['title_id'],
                ['embedding', 'document_version'],
            );
        });
    }
}
