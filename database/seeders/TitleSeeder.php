<?php

namespace Database\Seeders;

use App\Service\TitleService;
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
        $s3Key = env('SEED_CSV_S3_KEY');
        $handle = Storage::disk('s3')->readStream($s3Key);

        if (! $handle) {
            throw new Exception("Could not open seed CSV from S3: {$s3Key}");
        }

        $header = null;

        $titleService = new TitleService;
        $c = 0;
        try {
            while (($row = fgetcsv(stream: $handle, separator: '|')) !== false) {
                if (! $header) {
                    $header = $row;
                } else {
                    if (count($header) !== count($row)) {
                        var_dump('Broken row: '.json_encode($row));

                        continue;
                    }

                    $titleService->insertRow(array_combine($header, $row));
                }
                if ($c % 500 === 0) {
                    echo "Processed $c rows\r\n";
                }
                $c++;
            }
        } finally {
            fclose($handle);
        }

    }
}
