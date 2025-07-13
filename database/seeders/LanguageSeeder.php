<?php

namespace Database\Seeders;

use App\Models\Language;
use Exception;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = database_path('data/languages.csv');

        if (!file_exists($file) || !is_readable($file)) {
            throw new Exception("File not found");
        }

        $header = null;
        $data = [];

        if (($handle = fopen($file, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = $row;
                } else {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }


        foreach (array_chunk($data, 1000) as $chunkedData) {
            Language::upsert($chunkedData, ['code']);
        }
    }
}
