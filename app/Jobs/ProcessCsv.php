<?php

namespace App\Jobs;

use App\Service\MovieService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $uploadId;
    public array $headers;
    public array $rows;

    /**
     * Create a new job instance.
     */
    public function __construct($uploadId, $headers, $rows)
    {
        $this->uploadId = $uploadId;
        $this->headers = $headers;
        $this->rows = $rows;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        MovieService::insertBulk($this->rows, $this->headers, $this->uploadId);
    }
}
