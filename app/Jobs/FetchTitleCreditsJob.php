<?php

namespace App\Jobs;

use App\Models\Title;
use App\Services\TmdbApiService;
use App\Services\TmdbCredits\TmdbCreditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;

/**
 * Fetches and imports the credits for one title.
 *
 * Takes an id rather than a Title: queued jobs serialize their constructor
 * args, and a job that sits in the queue for minutes should read the row as it
 * is when it runs, not as it was when it was dispatched.
 */
class FetchTitleCreditsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(private readonly int $titleId)
    {
        $this->onQueue('enrichment');
    }

    public function middleware(): array
    {
        return [new RateLimited('tmdb')];
    }

    public function handle(TmdbApiService $tmdb, TmdbCreditService $credits): void
    {
        $title = Title::find($this->titleId);

        // Deleted since dispatch, or has no tmdb_id to fetch against. Neither
        // is retryable, so fall out quietly rather than burning the retries.
        if (! $title || ! $title->tmdb_id) {
            return;
        }

        $payload = $tmdb->getCredits($title->type, $title->tmdb_id);

        $written = $credits->import($title, $payload ?? []);

        // Stamped even when nothing was written. Plenty of titles have no
        // credits TMDB will give us, and without the stamp they'd be picked up
        // again on every run forever.
        $title->forceFill(['credits_filled_at' => now()])->save();

        Log::info("Imported {$written} credits for title: {$title->slug}");
    }
}
