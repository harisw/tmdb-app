<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
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
            $query->with(Title::detailRelations())->orderByDesc('created_at')->take(30);
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
                $genre->titles()
                    ->with(Title::detailRelations())
                    ->latest()
                    ->paginate(self::PAGINATE_SIZE)
            ),
        ]);
    }
}
