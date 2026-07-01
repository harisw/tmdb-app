<?php

namespace Database\Seeders;

use App\Services\TitleService;
use Artisan;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class TitleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $handle = $this->openCsvHandle();

        if (! $handle) {
            throw new Exception('Could not open seed CSV from local data folder or S3.');
        }

        $header = null;
        $batch = [];
        $batchSize = 50_000;
        $totalProcessed = 0;
        $totalInserted = 0;

        try {
            while (($row = fgetcsv(stream: $handle, separator: '|', escape: '\\')) !== false) {
                if (! $header) {
                    $header = $row;

                    continue;
                }

                if (count($header) !== count($row)) {
                    var_dump('Broken row: '.json_encode($row));

                    continue;
                }

                $batch[] = $row;
                $totalProcessed++;

                if (count($batch) >= $batchSize) {
                    $totalInserted += TitleService::insertBulk($batch, $header);
                    $batch = [];
                    echo "Processed {$totalProcessed} rows, inserted {$totalInserted}\r\n";
                }
            }

            if (! empty($batch)) {
                $totalInserted += TitleService::insertBulk($batch, $header);
            }
        } finally {
            fclose($handle);
        }

        echo "Done. Processed {$totalProcessed} rows, inserted {$totalInserted}\r\n";
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
        echo "Local seed: $localFile\n";
        if ($localFile && file_exists($localFile)) {
            $this->command->info("Using local CSV: {$localFile}");

            return fopen($localFile, 'r');
        }

        $s3Key = env('SEED_CSV_S3_KEY');
        if ($s3Key) {
            $this->command->info("Using S3 CSV: {$s3Key}");

            return Storage::disk('s3')->readStream($s3Key);
        }

        return null;
    }
}
