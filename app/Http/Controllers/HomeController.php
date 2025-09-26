<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Language;
use App\Models\Movie;
use Inertia\Inertia;

class HomeController extends Controller
{
    private const PAGINATE_SIZE = 30;

    public function index()
    {
        $movies = Movie::with(['genres', 'keywords'])
            ->latest()->paginate(self::PAGINATE_SIZE);

        return Inertia::render('Home', [
            'poster_url' => config('services.movie_db.img_url') . Movie::IMG_SMALL_URL,
            'backdrop_url' => config('services.movie_db.img_url') . Movie::IMG_LARGE_URL,
            'movies' => $movies
        ]);
    }

    public function getAllGenres(): \Inertia\Response
    {
        $genres = Genre::with(['movies' => function ($query) {
            $query->orderByDesc('created_at')->take(30);
        }])->get();

        return Inertia::render('AllGenres', [
            'allGenres' => $genres,
            'poster_url' => config('services.movie_db.img_url') . Movie::IMG_SMALL_URL,
            'backdrop_url' => config('services.movie_db.img_url') . Movie::IMG_LARGE_URL,
        ]);
    }

    public function getByGenre($slug = null): \Inertia\Response
    {
        $genre = Genre::whereSlug($slug)->first();

        return Inertia::render('MovieByGenre', [
            'genre' => $genre,
            'poster_url' => config('services.movie_db.img_url') . Movie::IMG_SMALL_URL,
            'backdrop_url' => config('services.movie_db.img_url') . Movie::IMG_LARGE_URL,
            'movies' => $genre->movies()->with(['genres', 'keywords'])->latest()->paginate(self::PAGINATE_SIZE),
        ]);
    }

    public function getByLanguage(): \Inertia\Response
    {
        $lang = request()->query('lang');
        $language = $lang ? Language::whereCode($lang)->first() : null;
        return Inertia::render('MovieByLanguage', [
            'languages' => Language::get(),
            'poster_url' => config('services.movie_db.img_url') . Movie::IMG_SMALL_URL,
            'backdrop_url' => config('services.movie_db.img_url') . Movie::IMG_LARGE_URL,
            'movies' => $language ? $language->movies()->with(['genres', 'keywords'])->latest()->paginate(self::PAGINATE_SIZE)
                : null,
        ]);
    }
}
