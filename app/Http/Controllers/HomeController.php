<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\Movie;
use App\Models\SearchableMovie;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class HomeController extends Controller
{
    private const PAGINATE_SIZE = 30;
    private const SEARCH_LIMIT = 10;

    public function index(): \Inertia\Response
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

    public function getByTag(): \Inertia\Response
    {
        $keyQuery = request()->query('tag');
        $tagOffset = request()->query('tagOffset', 0);
        $key = $keyQuery ? Keyword::whereSlug($keyQuery)->first() : null;

        $tags = Keyword::orderByDesc('count')->offset($tagOffset)
            ->take($tagOffset ? 35 : 50)
            ->get();

        return Inertia::render('MovieByKeyword', [
            'tags' => $tags,
            'poster_url' => config('services.movie_db.img_url') . Movie::IMG_SMALL_URL,
            'backdrop_url' => config('services.movie_db.img_url') . Movie::IMG_LARGE_URL,
            'movies' => $key ? $key->movies()->with(['genres', 'keywords'])->latest()->paginate(self::PAGINATE_SIZE)
                : null,
        ]);
    }

    public function search(): JsonResponse
    {
        $q = request()->query('q');
        $results = SearchableMovie::selectRaw("movie_id, ts_rank(search_vector, plainto_tsquery('english',(?))) as rank", [$q])
            ->whereRaw("search_vector @@ plainto_tsquery(?)", [$q])
            ->with('movie')
            ->orderByDesc('rank')
            ->take(30)->get()->pluck('movie');

        $hasMore = $results->count() > self::SEARCH_LIMIT;
        return response()->json([
            'hasMore' => $hasMore,
            'results' => $results->take(self::SEARCH_LIMIT)
        ]);
    }

    public function searchAll(): \Inertia\Response
    {
        $hasParams = request()->hasAny(['q', 'lang', 'gen', 'tag']);

        $results = collect([]);
        if ($hasParams) {
            $q = request()->query('q');

            $results = $q ? SearchableMovie::selectRaw("movie_id, ts_rank(search_vector, plainto_tsquery('english',(?))) as rank", [$q])
                ->whereRaw("search_vector @@ plainto_tsquery(?)", [$q])
                ->with('movie')
                ->orderByDesc('rank')
                ->take(100)->pluck('movie_id') : collect();

            $results = $this->applyFilters($results)->paginate(self::PAGINATE_SIZE);
        }

        return Inertia::render('MovieSearchAll', [
            'genres' => Genre::orderByDesc('count')->get(),
            'languages' => Language::orderByDesc('count')->get(),
            'tags' => Keyword::where('count', '>', 100)->orderByDesc('count')->get(),
            'poster_url' => config('services.movie_db.img_url') . Movie::IMG_SMALL_URL,
            'backdrop_url' => config('services.movie_db.img_url') . Movie::IMG_LARGE_URL,
            'movies' => $results,
        ]);
    }

    private function applyFilters(\Illuminate\Support\Collection $movieIds)
    {
        $query = Movie::query()->with(['genres', 'keywords']);
        $languageIds = request()->query('lang') ? Language::whereCode(request()->query('lang'))
            ->pluck('id') : [];

        if ($languageIds) {
            $query->whereIn('language_id', $languageIds);
        }

        $genreIds = request()->query('gen') ? Genre::whereSlug(request()->query('gen'))
            ->pluck('id') : [];
        if ($genreIds) {
            $query->whereHas('genres', function ($q) use ($genreIds) {
                $q->whereIn('genres.id', $genreIds);
            });
        }

        $keywordIds = request()->query('tag') ? Keyword::whereSlug(request()->query('tag'))
            ->pluck('id') : [];

        if ($keywordIds) {
            $query->whereHas('keywords', function ($q) use ($keywordIds) {
                $q->whereIn('keywords.id', $keywordIds);
            });
        }

        if ($movieIds->isNotEmpty()) {
            $query->whereIn('id', $movieIds);
        }

        return $query;
    }
}
