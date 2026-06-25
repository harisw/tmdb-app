<?php

namespace App\Jobs;

use App\Data\EnrichmentContext;
use App\Models\Title;
use App\Pipelines\Stages\ProcessKeywords;
use App\Pipelines\Stages\ProcessWatchProviders;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Pipeline;

class EnrichTitleJob implements ShouldQueue
{
    use Queueable;

    public function middleware(): array
    {
        return [new RateLimited('tmdb')];
    }

    public function __construct(private readonly Title $title)
    {
        $this->onQueue('enrichment');
    }

    public function handle(): void
    {
        $context = new EnrichmentContext($this->title);

        Pipeline::send($context)
            ->through([
                ProcessKeywords::class,
                ProcessWatchProviders::class,
            ])
            ->thenReturn();

        Log::info('Finished enriching title: '.$this->title->slug);
    }
}
