<?php

namespace App\Http\Controllers;

use App\Domain\Enums\TitleSort;
use App\Http\Requests\BrowseTitlesRequest;
use App\Http\Resources\TitleResource;
use App\Models\Genre;
use App\Models\Title;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Inertia\Inertia;
use Inertia\Response;

class GenreController extends Controller
{
    private const ROW_SIZE = 15;

    public function index(): Response
    {
        $genres = Genre::query()
            ->where('count', '>', 0)
            ->with(['titles' => fn (BelongsToMany $titles) => $titles
                ->with(Title::cardRelations())
                ->sorted(TitleSort::POPULAR)
                ->limit(self::ROW_SIZE),
            ])
            ->orderByDesc('count')
            ->get()
            ->filter(fn (Genre $genre) => $genre->titles->isNotEmpty())
            ->values()
            ->map(fn (Genre $genre) => [
                ...$genre->toArray(),
                'titles' => TitleResource::collection($genre->titles)->resolve(),
            ]);

        return Inertia::render('AllGenres', [
            'allGenres' => $genres,
        ]);
    }

    public function show(BrowseTitlesRequest $request, Genre $genre): Response
    {
        $sort = $request->sort();

        return Inertia::render('TitleByGenre', [
            'genre' => $genre,
            'titles' => TitleResource::collection(
                $genre->titles()
                    ->with(Title::cardRelations())
                    ->sorted($sort)
                    ->paginate(self::PAGINATE_SIZE)
            ),
            'sort' => $sort->value,
            'sorts' => TitleSort::options(),
        ]);
    }
}
