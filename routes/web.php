<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
Route::get('/genre/all', [HomeController::class, 'getAllGenres'])->name('movie-all-genres');
Route::get('/genre/{slug}', [HomeController::class, 'getByGenre'])->name('movie-by-genre');
Route::get('/languages', [HomeController::class, 'getByLanguage'])->name('movie-by-language');
Route::get('/tags', [HomeController::class, 'getByTag'])->name('movie-by-tag');
