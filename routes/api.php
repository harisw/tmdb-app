<?php


use App\Http\Controllers\CsvUploadController;

Route::post('/csv-upload', [CsvUploadController::class, 'upload']);
Route::get('/csv-upload/{uploadId}/progress', [CsvUploadController::class, 'progress']);
