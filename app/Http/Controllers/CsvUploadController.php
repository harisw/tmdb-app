<?php

namespace App\Http\Controllers;

use App\Service\CsvUploadService;
use Cache;

class CsvUploadController extends Controller
{

    public function upload(\Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid file',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = CsvUploadService::for($request->file('csv_file'));

        return response()->json(
            array_merge($result, ['message' => 'Upload started!'])
        );
    }

    public function progress($uploadId): \Illuminate\Http\JsonResponse
    {
        $progress = Cache::get("csv_upload_{$uploadId}");

        if (!$progress) {
            return response()->json([
                'message' => 'Upload not found'
            ], 404);
        }

        return response()->json($progress);
    }
}
