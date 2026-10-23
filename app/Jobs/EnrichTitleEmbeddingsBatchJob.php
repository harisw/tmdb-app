<?php

namespace App\Jobs;

use App\Models\Title;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnrichTitleEmbeddingsBatchJob implements ShouldQueue
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
        $titleIds = Title::withoutGlobalScopes()
            ->whereNull('embedding')
            ->limit($this->limit)
            ->pluck('id');

        if ($titleIds->isEmpty()) {
            return;
        }

        Log::info("Dispatching title embeddings for {$titleIds->count()} titles");

        $titleIds->chunk(self::CHUNK_SIZE)
            ->each(fn ($chunk) => GenerateTitleEmbeddingsChunkJob::dispatch($chunk->values()->all()));
    }
}
