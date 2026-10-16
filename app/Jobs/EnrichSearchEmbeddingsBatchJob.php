<?php

namespace App\Jobs;

use App\Models\Title;
use App\Services\Search\SearchDocumentBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnrichSearchEmbeddingsBatchJob implements ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 100;

    public function __construct(private readonly int $limit = 500) {}

    public function handle(): void
    {
        $titleIds = Title::withoutGlobalScopes()
            ->whereDoesntHave('searchEmbedding',
                fn ($query) => $query->where('document_version', SearchDocumentBuilder::VERSION))
            ->orderByDesc('popularity')
            ->limit($this->limit)
            ->pluck('id');

        if ($titleIds->isEmpty()) {
            return;
        }

        Log::info("Dispatching search embeddings for {$titleIds->count()} titles");

        $titleIds->chunk(self::CHUNK_SIZE)
            ->each(fn ($chunk) => GenerateSearchEmbeddingsChunkJob::dispatch($chunk->values()->all()));
    }
}
