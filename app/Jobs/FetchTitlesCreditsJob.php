<?php

namespace App\Jobs;

use App\Models\Title;
use App\Services\TmdbApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchTitlesCreditsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $limit,
        private TmdbApiService $tmdbService) {}

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
