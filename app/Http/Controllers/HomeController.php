<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Movie;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $movies = Movie::with(['genres', 'keywords'])
            ->latest()->take(100)->get();

        return Inertia::render('Home', [
            'poster_url' => config('services.movie_db.img_url').Movie::IMG_SMALL_URL,
            'backdrop_url' => config('services.movie_db.img_url').Movie::IMG_LARGE_URL,
            'items' => $movies
        ]);
    }

    public function getAllGenres()
    {
        $genre = Genre::get();
//        $movies = Movie::with(['genres', 'keywords'])
//            ->latest()->take(100)->get();

        return Inertia::render('MovieByGenre', [
            'genre' => $genre,
            'poster_url' => config('services.movie_db.img_url').Movie::IMG_SMALL_URL,
            'backdrop_url' => config('services.movie_db.img_url').Movie::IMG_LARGE_URL,
        ]);
    }

    public function getByGenre($slug = null)
    {
        $genre = Genre::whereSlug($slug)->first();
//        $movies = Movie::with(['genres', 'keywords'])
//            ->latest()->take(100)->get();
//        dd($genre->movies()->with(['genres', 'keywords'])->latest()->take(100)->get());
        return Inertia::render('MovieByGenre', [
            'genre' => $genre,
            'poster_url' => config('services.movie_db.img_url').Movie::IMG_SMALL_URL,
            'backdrop_url' => config('services.movie_db.img_url').Movie::IMG_LARGE_URL,
            'items' => $genre->movies()->with(['genres', 'keywords'])->latest()->take(100)->get(),
        ]);
    }
}
