<?php

namespace App\Jobs;

use App\Models\Title;
use DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Fans out one MaterializeTitleSimilaritiesJob per embedded title.
 */
class MaterializeTitleSimilaritiesBatchJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $onlyMissing  Skip titles that already have similarities
     *                             materialised (backfill), rather than rebuilding
     *                             every embedded title.
     */
    public function __construct(
        private readonly int $limit = 1000,
        private readonly bool $onlyMissing = false,
    ) {
        $this->onQueue('embeddings');
    }

    public function handle(): void
    {
        $titleIds = Title::query()
            ->withoutGlobalScopes()
            ->whereNotNull('embedding')
            ->when($this->onlyMissing, function ($query) {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('title_similarities')
                        ->whereColumn('title_similarities.title_id', 'titles.id');
                });
            })
            ->limit($this->limit)
            ->pluck('id');

        Log::info("Dispatching similarity materialization for {$titleIds->count()} titles");

        $titleIds->each(fn (int $id) => MaterializeTitleSimilaritiesJob::dispatch($id));
    }
}
