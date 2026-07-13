<?php

namespace App\Jobs;

use App\Models\Title;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Picks the next slice of titles needing credits and fans out one
 * FetchTitleCreditsJob per title.
 *
 * The fan-out is the point: a single job doing N sequential TMDB calls means
 * one failed request retries the whole batch, and a slow batch holds a worker
 * for minutes. Per-title jobs give each HTTP call its own retry budget and let
 * the queue spread them across workers under the shared 'tmdb' rate limiter.
 */
class FetchTitlesCreditsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $limit = 100)
    {
        $this->onQueue('enrichment');
    }

    public function handle(): void
    {
        // credits_filled_at is the sole driver. Selecting on the absence of a
        // people relation instead would (a) make the staleness branch dead,
        // since a title with credits always has people, and (b) re-fetch a
        // title TMDB genuinely has no credits for on every single run.
        $titleIds = Title::query()
            // tmdb_id is nullable — CSV-imported titles may never have had one,
            // and there is nothing to fetch credits against without it.
            ->whereNotNull('tmdb_id')
            ->where(function ($query) {
                $query->whereNull('credits_filled_at')
                    ->orWhere('credits_filled_at', '<', now()->subMonths(3));
            })
            ->orderByDesc('popularity')
            ->limit($this->limit)
            ->pluck('id');

        Log::info("Dispatching credit fetch for {$titleIds->count()} titles");

        $titleIds->each(fn (int $id) => FetchTitleCreditsJob::dispatch($id));
    }
}
