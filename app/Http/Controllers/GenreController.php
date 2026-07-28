<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Title;
use Inertia\Inertia;
use Inertia\Response;

class GenreController extends Controller
{
    private const PAGINATE_SIZE = 30;

    public function index(): Response
    {
        $genres = Genre::with(['titles' => function ($query) {
            $query->with(['titleProviders' => function ($q) {
                $q->with('provider')->uniqueByProvider();
            }])->orderByDesc('created_at')->take(30);
        }])->get();

        return Inertia::render('AllGenres', [
            'allGenres' => $genres,
        ]);
    }

    public function show(Genre $genre): Response
    {
        return Inertia::render('TitleByGenre', [
            'genre' => $genre,
            'titles' => $genre->titles()->with(Title::detailRelations())->latest()->paginate(self::PAGINATE_SIZE),
        ]);
    }
}
