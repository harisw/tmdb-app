<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Movie;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $movies = Movie::with('genres')
            ->latest()->take(100)->get();

        return Inertia::render('Home', [
//            'genres' => Genre::take(5)->get(), // dropdown data
            'poster_url' => config('services.movie_db.img_url').Movie::IMG_SMALL_URL,
            'backdrop_url' => config('services.movie_db.img_url').Movie::IMG_LARGE_URL,
            'items' => $movies
        ]);
    }
}
