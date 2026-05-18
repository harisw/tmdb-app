<?php

namespace App\Jobs;

use App\Data\EnrichmentContext;
use App\Models\Title;
use App\Pipelines\Stages\ProcessKeywords;
use App\Pipelines\Stages\ProcessWatchProviders;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Pipeline;

class EnrichTitleJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private Title $title) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $context = new EnrichmentContext($this->title);

        Pipeline::send($context)
            ->through([
                ProcessKeywords::class,
                ProcessWatchProviders::class,
            ])
            ->thenReturn();

        \Log::info('FInished syncing keywords '.$this->title->slug);
    }
}
