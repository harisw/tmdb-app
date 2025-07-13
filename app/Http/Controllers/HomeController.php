<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $movies = Movie::latest()->take(10)->get();

        return Inertia::render('Home', [
            'items' => $movies
        ]);
    }
}
