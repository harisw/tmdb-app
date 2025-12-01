<?php

namespace App\Service;

use App\Jobs\ProcessCsv;
use Cache;
use Str;

class CsvUploadService
{

    /**
     * @param $file
     * @return array
     */
    public static function for($file): array
    {
        $uploadId = Str::uuid()->toString();

        $csv = array_map('str_getcsv', file($file->getPathname()));
        $headers = array_shift($csv);

        $total = count($csv);

        // Initialize progress tracking in cache
        Cache::put("csv_upload_{$uploadId}", [
            'status' => 'processing',
            'total_rows' => $total,
            'processed_rows' => 0,
            'error' => null
        ], now()->addHours(1));

        ProcessCsv::dispatch($uploadId, $headers, $csv);

        return [
            'uploadId' => $uploadId,
            'totalRows' => $total,
        ];
    }
}
