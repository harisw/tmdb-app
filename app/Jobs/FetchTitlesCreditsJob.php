<?php

namespace App\Jobs;

use App\Data\EnrichmentContext;
use App\Models\Title;
use App\Pipelines\Stages\ProcessKeywords;
use App\Pipelines\Stages\ProcessWatchProviders;
use App\Services\TmdbService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Pipeline;

class FetchTitlesCreditsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $limit = 50,
    private TmdbService $tmdbService) {}

    public function handle(): void
    {
        $titlesWithoutCredits = Title::whereDoesntHave('people')
            ->where(function ($query) {
                $query->where('credits_filled_at', '<', now()->subMonths(3))
                    ->orWhereNull('credits_filled_at');
            })
            ->orderByDesc('popularity')
            ->limit($this->limit)
            ->get();
        Log::info("Start Fetching {$titlesWithoutCredits} titles");
        foreach ($titlesWithoutCredits as $title) {
            try {
                $this->fillCredits($title);
            } catch (\Throwable $t) {
                Log::error("Error Title: {$title->slug}");
            }
        }
    }

    private function fillCredits(Title $title)
    {
        $credits = $this->tmdbService->getCredits($title->type, $title->tmdb_id);


    }
}
