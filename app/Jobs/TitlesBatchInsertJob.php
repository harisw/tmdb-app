<?php

namespace App\Jobs;

use App\Services\TitleService;
use Artisan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TitlesBatchInsertJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $batchSize = 10_000) {}

    public function handle(): void
    {
        $handle = $this->openCsvHandle();

        if (! $handle) {
            throw new Exception('Could not open seed CSV from local data folder or S3.');
        }

        $header = null;
        $batch = [];
        $totalProcessed = 0;
        $totalInserted = 0;
        $i = 0;
        try {
            Log::info('Start processing batch');
            while (($row = fgetcsv(stream: $handle, separator: '|', escape: '\\')) !== false) {
                $i++;
                if (! $header) {
                    $header = $row;

                    continue;
                }
                Log::info("Processing row $i\n");
                if (count($header) !== count($row)) {
                    Log::error('Broken row: '.json_encode($row));

                    continue;
                }

                $batch[] = $row;
                $totalProcessed++;

                if (count($batch) >= $this->batchSize) {
                    $totalInserted += TitleService::insertBulk($batch, $header);
                    $batch = [];
                    Log::info("Processed {$totalProcessed} rows, inserted {$totalInserted}\r\n");
                }
            }

            if (! empty($batch)) {
                $totalInserted += TitleService::insertBulk($batch, $header);
            }
        } finally {
            fclose($handle);
        }

        echo "Finished TitleBatchInsertJob Processed {$totalProcessed} rows, inserted {$totalInserted}\r\n";
        Artisan::call('titles:refresh-searchable');
    }

    private function openCsvHandle(): mixed
    {
        $localFile = env('SEED_CSV_LOCAL');

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

        $s3Key = env('SEED_CSV_S3_KEY');
        if ($s3Key) {
            Log::info("Using S3 CSV: {$s3Key}");

            return Storage::disk('s3')->readStream($s3Key);
        }

        return null;
    }
}
