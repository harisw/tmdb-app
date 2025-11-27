<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
Route::get('/genre/all', [HomeController::class, 'getAllGenres'])->name('movie-all-genres');
Route::get('/genre/{slug}', [HomeController::class, 'getByGenre'])->name('movie-by-genre');
Route::get('/languages', [HomeController::class, 'getByLanguage'])->name('movie-by-language');
Route::get('/tags', [HomeController::class, 'getByTag'])->name('movie-by-tag');
Route::get('/search', [HomeController::class, 'search'])->name('search-movie');
Route::get('/search-all', [HomeController::class, 'searchAll'])->name('search-all-movie');

if (config('auth.allow_register')) {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
}
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
