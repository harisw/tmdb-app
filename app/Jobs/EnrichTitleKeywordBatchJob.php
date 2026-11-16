<?php

namespace App\Jobs;

use App\Models\Title;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class EnrichTitleKeywordBatchJob implements ShouldQueue
{
    use Queueable;

    private const STALE_AFTER_MONTHS = 3;

    public function __construct(private readonly int $limit = 500) {}

    public function handle(): void
    {
        $jobs = Title::withoutGlobalScopes()
            ->whereNotNull('tmdb_id')
            ->whereDoesntHave('keywords')
            ->where('enriched_at', '<', now()->subMonths(self::STALE_AFTER_MONTHS))
            ->orderByDesc('popularity')
            ->limit($this->limit)
            ->get()
            ->map(fn (Title $title) => new EnrichTitleJob($title));

        if ($jobs->isEmpty()) {
            return;
        }

        Log::info("Start backfilling keywords for {$jobs->count()} titles");

        Bus::batch($jobs)
            ->name('Enrich Title Keywords Batch')
            ->onQueue('enrichment')
            ->allowFailures()
            ->dispatch();
    }
}
