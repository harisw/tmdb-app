<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
Route::get('/genre/all', [GenreController::class, 'index'])->name('title-all-genres');
Route::get('/genre/{genre}', [GenreController::class, 'show'])->name('title-by-genre');
Route::get('/languages', [LanguageController::class, 'index'])->name('title-by-language');
Route::get('/provider/all', [ProviderController::class, 'index'])->name('provider-all');
Route::get('/provider/{provider}', [ProviderController::class, 'show'])->name('title-by-provider');
Route::get('/tags', [KeywordController::class, 'index'])->name('title-by-tag');
Route::middleware('throttle:40,1')->group(function () {
    Route::get('/search', [SearchController::class, 'search'])->name('search-title');
});

Route::get('/search-all', [SearchController::class, 'searchAll'])->name('search-all-title');

if (config('auth.allow_register')) {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
}
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
