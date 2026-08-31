<?php

namespace App\Jobs;

use App\Models\Title;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnrichTitleCreditsBatchJob implements ShouldQueue
{
    use Queueable;

    private const RETRY_MONTHS_THRESHOLD = 6;

    public function __construct(private readonly int $limit = 100)
    {
        $this->onQueue('enrichment');
    }

    public function handle(): void
    {
        $titleIds = Title::query()
            ->whereNotNull('tmdb_id')
            ->where(function ($query) {
                $query->whereNull('credits_filled_at')
                    ->orWhere('credits_filled_at', '<', now()->subMonths(self::RETRY_MONTHS_THRESHOLD));
            })
            ->orderByDesc('popularity')
            ->limit($this->limit)
            ->pluck('id');

        Log::info("Dispatching credit fetch for {$titleIds->count()} titles");

        $titleIds->each(fn (int $id) => EnrichTitleCreditsJob::dispatch($id));
    }
}
