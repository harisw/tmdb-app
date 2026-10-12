<?php

namespace App\Jobs;

use App\Models\Title;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class EnrichTitleProvidersLinkBatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $limit = 100) {}

    public function handle(): void
    {
        $jobs = Title::whereDoesntHave('titleProviders', function ($q) {
            $q->whereNotNull('link');
        })
            // ->where(function ($query) {
            //     $query->where('enriched_at', '<', now()->subMonths(3))
            //         ->orWhereNull('enriched_at');
            // })
            ->orderByDesc('popularity')
            ->limit($this->limit)
            ->get()
            ->map(fn ($title) => new EnrichTitleProvidersLinkJob($title));

        Log::info("Start Enriching {$jobs->count()} titles");

        Bus::batch($jobs)
            ->name('Enrich Titles Providers Link Batch')
            ->allowFailures()
            ->dispatch();
    }
}
