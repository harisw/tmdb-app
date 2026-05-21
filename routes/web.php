<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
Route::get('/genre/all', [HomeController::class, 'getAllGenres'])->name('title-all-genres');
Route::get('/genre/{genre}', [HomeController::class, 'getByGenre'])->name('title-by-genre');
Route::get('/languages', [HomeController::class, 'getByLanguage'])->name('title-by-language');
Route::get('/tags', [HomeController::class, 'getByTag'])->name('title-by-tag');
Route::middleware('throttle:40,1')->group(function () {
    Route::get('/search', [HomeController::class, 'search'])->name('search-title');
});

Route::get('/search-all', [HomeController::class, 'searchAll'])->name('search-all-title');

if (config('auth.allow_register')) {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
}
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
