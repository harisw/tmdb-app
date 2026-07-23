<?php

use App\Http\Controllers\FeatureFlagController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::get('/', [HomeController::class, 'index']);
Route::get('/genre/all', [GenreController::class, 'index'])->name('all-genres');
Route::get('/genre/{genre}', [GenreController::class, 'show'])->name('titles-by-genre');
Route::get('/languages', [LanguageController::class, 'index'])->name('titles-by-language');
Route::get('/provider/all', [ProviderController::class, 'index'])->name('all-providers');
Route::get('/provider/{provider}', [ProviderController::class, 'show'])->name('titles-by-provider');
Route::get('/tags', [KeywordController::class, 'index'])->name('all-tags');
Route::get('/tag/{keyword}', [KeywordController::class, 'show'])->name('titles-by-keyword');
Route::middleware('throttle:40,1')->group(function () {
    Route::get('/search', [SearchController::class, 'search'])->name('search-title');
});

Route::get('/search-all', [SearchController::class, 'searchAll'])->name('search-all-title');

Route::middleware(['auth', 'can:admin'])->prefix('admin')->group(function () {
    Route::get('/feature-flags', [FeatureFlagController::class, 'index'])->name('feature-flags');
    Route::patch('/feature-flags/{flag}', [FeatureFlagController::class, 'update'])
        ->name('feature-flags.update');
});
