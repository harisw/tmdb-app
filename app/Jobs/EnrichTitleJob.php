<?php

namespace App\Jobs;

use App\DTOs\TmdbApi\TmdbTitleData;
use App\Models\Title;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichTitleJob implements ShouldQueue
{
    use Queueable;

    private Title $title;

    private TmdbTitleData $titleData;

    /**
     * Create a new job instance.
     */
    public function __construct(Title $title, TmdbTitleData $titleData)
    {
        $this->title = $title;
        $this->titleData = $titleData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {}
}
