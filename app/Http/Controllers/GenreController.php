<?php

namespace App\Http\Controllers;

use App\Domain\Enums\TitleSort;
use App\Http\Requests\BrowseTitlesRequest;
use App\Http\Resources\TitleResource;
use App\Models\Genre;
use App\Models\Title;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class GenreController extends Controller
{
    private const ROW_SIZE = 15;

    public function index(): Response
    {
        $genres = Genre::query()
            ->where('count', '>', 0)
            ->orderByDesc('count')
            ->get();

        $rows = $this->mostPopularTitlesPerGenre($genres);

        $genres = $genres
            ->filter(fn (Genre $genre) => $rows->has($genre->id))
            ->values()
            ->map(fn (Genre $genre) => [
                ...$genre->toArray(),
                'titles' => TitleResource::collection($rows[$genre->id])->resolve(),
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
                    ->cursorPaginate(self::PAGINATE_SIZE)
            ),
            'sort' => $sort->value,
            'sorts' => TitleSort::options(),
        ]);
    }

    /**
     * @param  Collection<int, Genre>  $genres
     * @return Collection<int, Collection<int, Title>>
     */
    private function mostPopularTitlesPerGenre(Collection $genres): Collection
    {
        return Title::perGenre(Title::sorted(TitleSort::POPULAR)->limit(self::ROW_SIZE), $genres->pluck('id'))
            ->sorted(TitleSort::POPULAR)
            ->with(Title::cardRelations())
            ->get()
            ->groupBy('genre_id');
    }
}
