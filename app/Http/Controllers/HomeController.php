<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $movies = Movie::latest()->take(100)->get();

        return Inertia::render('Home', [
            'poster_url' => config('services.movie_db.img_url').Movie::IMG_SMALL_URL,
            'items' => $movies
        ]);
    }
}
