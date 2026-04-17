<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\SearchableTitle;
use App\Models\Title;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const PAGINATE_SIZE = 30;

    private const SEARCH_LIMIT = 10;

    public function index(): Response
    {
        $titles = Title::with(['genres', 'keywords'])
            ->latest()->paginate(self::PAGINATE_SIZE);

        return Inertia::render('Home', [
            'poster_url' => config('services.title_db.img_url').Title::IMG_SMALL_URL,
            'backdrop_url' => config('services.title_db.img_url').Title::IMG_LARGE_URL,
            'titles' => $titles,
        ]);
    }

    public function getAllGenres(): Response
    {
        $genres = Genre::with(['titles' => function ($query) {
            $query->orderByDesc('created_at')->take(30);
        }])->get();

        return Inertia::render('AllGenres', [
            'allGenres' => $genres,
            'poster_url' => config('services.title_db.img_url').Title::IMG_SMALL_URL,
            'backdrop_url' => config('services.title_db.img_url').Title::IMG_LARGE_URL,
        ]);
    }

    public function getByGenre(Genre $genre): Response
    {
        return Inertia::render('TitleByGenre', [
            'genre' => $genre,
            'poster_url' => config('services.title_db.img_url').Title::IMG_SMALL_URL,
            'backdrop_url' => config('services.title_db.img_url').Title::IMG_LARGE_URL,
            'titles' => $genre->titles()->with(['genres', 'keywords'])->latest()->paginate(self::PAGINATE_SIZE),
        ]);
    }

    public function getByLanguage(): Response
    {
        $lang = request()->query('lang');
        $language = $lang ? Language::whereCode($lang)->first() : null;

        return Inertia::render('TitleByLanguage', [
            'languages' => Language::orderByDesc('count')->get(),
            'poster_url' => config('services.title_db.img_url').Title::IMG_SMALL_URL,
            'backdrop_url' => config('services.title_db.img_url').Title::IMG_LARGE_URL,
            'titles' => $language ? $language->titles()->with(['genres', 'keywords'])->latest()->paginate(self::PAGINATE_SIZE)
                : null,
        ]);
    }

    public function getByTag(): Response
    {
        $keyQuery = request()->query('tag');
        $tagOffset = request()->query('tagOffset', 0);
        $key = $keyQuery ? Keyword::whereSlug($keyQuery)->first() : null;

        $tags = Keyword::orderByDesc('count')->offset($tagOffset)
            ->take($tagOffset ? 35 : 50)
            ->get();

        return Inertia::render('TitleByKeyword', [
            'tags' => $tags,
            'poster_url' => config('services.title_db.img_url').Title::IMG_SMALL_URL,
            'backdrop_url' => config('services.title_db.img_url').Title::IMG_LARGE_URL,
            'titles' => $key ? $key->titles()->with(['genres', 'keywords'])->latest()->paginate(self::PAGINATE_SIZE)
                : null,
        ]);
    }

    public function search(): JsonResponse
    {
        $q = request()->query('q');
        $results = SearchableTitle::selectRaw("title_id, ts_rank(search_vector, plainto_tsquery('english',(?))) as rank", [$q])
            ->whereRaw('search_vector @@ plainto_tsquery(?)', [$q])
            ->with('title')
            ->orderByDesc('rank')
            ->take(30)->get()->pluck('title');

        $hasMore = $results->count() > self::SEARCH_LIMIT;

        return response()->json([
            'hasMore' => $hasMore,
            'results' => $results->take(self::SEARCH_LIMIT),
        ]);
    }

    public function searchAll(): Response
    {
        $hasParams = request()->hasAny(['q', 'lang', 'gen', 'tag']);

        $results = collect([]);
        if ($hasParams) {
            $q = request()->query('q');

            $results = $q ? SearchableTitle::selectRaw("title_id, ts_rank(search_vector, plainto_tsquery('english',(?))) as rank", [$q])
                ->whereRaw('search_vector @@ plainto_tsquery(?)', [$q])
                ->with('title')
                ->orderByDesc('rank')
                ->take(100)->pluck('title_id') : collect();

            $results = $this->applyFilters($results)->paginate(self::PAGINATE_SIZE);
        }

        return Inertia::render('TitleSearchAll', [
            'genres' => Genre::orderByDesc('count')->get(),
            'languages' => Language::orderByDesc('count')->get(),
            'tags' => Keyword::where('count', '>', 100)->orderByDesc('count')->get(),
            'poster_url' => config('services.title_db.img_url').Title::IMG_SMALL_URL,
            'backdrop_url' => config('services.title_db.img_url').Title::IMG_LARGE_URL,
            'titles' => $results,
        ]);
    }

    private function applyFilters(Collection $titleIds)
    {
        $query = Title::query()->with(['genres', 'keywords']);
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

        if ($titleIds->isNotEmpty()) {
            $query->whereIn('id', $titleIds);
        }

        return $query;
    }
}
