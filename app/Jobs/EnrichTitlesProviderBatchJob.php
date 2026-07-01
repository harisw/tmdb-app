<?php

namespace App\Jobs;

use App\Models\Title;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class EnrichTitlesProviderBatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $limit = 50) {}

    public function handle(): void
    {
        $jobs = Title::whereDoesntHave('providers')
            ->where(function ($query) {
                $query->where('enriched_at', '<', now()->subMonths(3))
                    ->orWhereNull('enriched_at');
            })
            ->orderByDesc('popularity')
            ->limit($this->limit)
            ->get()
            ->map(fn ($title) => new EnrichTitleJob($title));

        Log::info("Start Enriching {$jobs->count()} titles");

        Bus::batch($jobs)
            ->name('Enrich Titles Providers Batch')
            ->allowFailures()
            ->dispatch();
    }
}
