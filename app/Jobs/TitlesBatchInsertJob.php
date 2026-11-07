<?php

namespace App\Jobs;

use App\Services\TitleService;
use Artisan;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TitlesBatchInsertJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    private array $batch = [];

    private int $totalProcessed = 0;

    private int $totalInserted = 0;

    private TitleService $titleService;

    public function __construct(private readonly int $batchSize = 5000) {}

    public function handle(): void
    {
        ini_set('memory_limit', '256M');
        $this->titleService = new TitleService;

        $handle = $this->openCsvHandle();

        if (! $handle) {
            throw new Exception('Could not open seed CSV from local data folder or S3.');
        }

        $header = null;
        try {
            Log::info('Start processing batch');
            while (($row = fgetcsv(stream: $handle, separator: '|', escape: '\\')) !== false) {
                try {
                    $this->processRow($row, $header);
                } catch (Exception $e) {
                    Log::error('Error row: '.json_encode($row, JSON_PRETTY_PRINT));
                    report($e);
                }
            }

            if (! empty($this->batch)) {
                $this->totalInserted += $this->titleService->insert($this->batch, $header);
            }
        } finally {
            fclose($handle);
        }

        Log::info("Finished TitleBatchInsertJob Processed {$this->totalProcessed} rows, inserted {$this->totalInserted}\r\n");
        Artisan::call('titles:refresh-searchable');
    }

    private function processRow($row, &$header): void
    {
        if (! $header) {
            $header = $row;

            return;
        }
        if (count($header) !== count($row)) {
            Log::error('Broken row: '.json_encode($row));

            return;
        }

        $this->batch[] = $row;
        $this->totalProcessed++;

        if (count($this->batch) >= $this->batchSize) {
            $this->totalInserted += $this->titleService->insert($this->batch, $header);
            $this->batch = [];
            Log::info("Processed {$this->totalProcessed} rows, inserted {$this->totalInserted}\r\n");
        }
    }

    private function openCsvHandle(): mixed
    {
        $localFile = config('services.seed.csv_local');

        if (! $localFile) {
            $dataDir = base_path('database/seeders/data');
            $found = glob("{$dataDir}/*.csv");
            $localFile = $found ? $found[0] : null;
        } else {
            $localFile = base_path("database/seeders/data/{$localFile}");
        }
        if ($localFile && file_exists($localFile)) {
            Log::info("Using local CSV: {$localFile}");

            return fopen($localFile, 'r');
        }

        $s3Key = config('services.seed.csv_s3_key');
        if ($s3Key) {
            Log::info("Using S3 CSV: {$s3Key}");

            return Storage::disk('s3')->readStream($s3Key);
        }

        return null;
    }
}
