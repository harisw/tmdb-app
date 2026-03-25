<?php

namespace Database\Seeders;

use App\Service\MovieService;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class MovieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $s3Key = env('SEED_CSV_S3_KEY');
        $handle = Storage::disk('s3')->readStream($s3Key);

        if (!$handle) {
            throw new Exception("Could not open seed CSV from S3: {$s3Key}");
        }

        $header = null;
        $data = [];

        try {
            while (($row = fgetcsv(stream: $handle, separator: '|')) !== false) {
                if (!$header) {
                    $header = $row;
                } else {
                    if (count($header) !== count($row)) {
                        var_dump('Broken row: ' . json_encode($row));

                        continue;
                    }
                    $data[] = array_combine($header, $row);
                }
            }
        } finally {
            fclose($handle);
        }

        $movieService = new MovieService;
        foreach ($data as $i => $row) {

            $movieService->insertRow($row);

            if ($i % 100 === 0) {
                echo "Processed $i rows\r\n";
            }
        }
    }
}
