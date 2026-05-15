<?php

namespace App\Jobs;

use App\Data\EnrichmentContext;
use App\Models\Title;
use App\Pipelines\Stages\ProcessKeywords;
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

        $context = Pipeline::send($context)
            ->through([
                ProcessKeywords::class,
            ])
            ->thenReturn();

        $this->title->keywords()->sync($context->keywordIds);
        \Log::info('FInished syncing keywords '.$this->title->slug);
    }
}
