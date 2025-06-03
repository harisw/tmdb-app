<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MovieController extends Controller
{

    public function index()
    {
        $movies = Movie::with(['companies', 'genres', 'keywords'])
            ->orderByDesc('popularity')
            ->take(100)->get();

        return Inertia::render('index', [
            'movies' => $movies
        ]);
    }
}
