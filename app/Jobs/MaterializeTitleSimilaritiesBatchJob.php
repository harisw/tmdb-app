<?php

namespace App\Jobs;

use App\Models\Title;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Fans out one MaterializeTitleSimilaritiesJob per embedded title not materialized in the last week, oldest first.
 */
class MaterializeTitleSimilaritiesBatchJob implements ShouldQueue
{
    use Queueable;

    private const STALE_AFTER_DAYS = 7;

    public function __construct(
        private readonly int $limit = 1000,
        private readonly bool $includeFresh = false,
    ) {
        $this->onQueue('embeddings');
    }

    public function handle(): void
    {
        $titleIds = Title::query()
            ->withoutGlobalScopes()
            ->whereNotNull('embedding')
            ->unless($this->includeFresh, fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('similarities_materialized_at')
                ->orWhere('similarities_materialized_at', '<', now()->subDays(self::STALE_AFTER_DAYS))))
            ->orderByRaw('similarities_materialized_at asc nulls first')
            ->orderBy('id')
            ->limit($this->limit)
            ->pluck('id');

        Log::info("Dispatching similarity materialization for {$titleIds->count()} titles");

        $titleIds->each(fn (int $id) => MaterializeTitleSimilaritiesJob::dispatch($id));
    }
}
