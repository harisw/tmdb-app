<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
use App\Models\Genre;
use Inertia\Inertia;
use Inertia\Response;

class GenreController extends Controller
{
    public function index(): Response
    {
        $genres = Genre::with(['titles' => function ($query) {
            $query->listing()->paginate(self::PAGINATE_SIZE);
        }])->get()->map(fn (Genre $genre) => array_merge($genre->toArray(), [
            'titles' => TitleResource::collection($genre->titles)->resolve(),
        ]));

        return Inertia::render('AllGenres', [
            'allGenres' => $genres,
        ]);
    }

    public function show(Genre $genre): Response
    {
        return Inertia::render('TitleByGenre', [
            'genre' => $genre,
            'titles' => TitleResource::collection(
                $genre->titles()->listing()->paginate(self::PAGINATE_SIZE)
            ),
        ]);
    }
}
