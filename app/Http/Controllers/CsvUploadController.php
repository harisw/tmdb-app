<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadCsvRequest;
use App\Services\CsvUploadService;
use Cache;
use Illuminate\Http\JsonResponse;

class CsvUploadController extends Controller
{
    public function upload(UploadCsvRequest $request): JsonResponse
    {
        $result = CsvUploadService::for($request->file('csv_file'));

        return response()->json(
            array_merge($result, ['message' => 'Upload started!'])
        );
    }

    public function progress($uploadId): JsonResponse
    {
        $progress = Cache::get("csv_upload_{$uploadId}");

        if (! $progress) {
            return response()->json([
                'message' => 'Upload not found',
            ], 404);
        }

        return response()->json($progress);
    }
}
