<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\SearchableTitle;
use App\Models\Title;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    private const PAGINATE_SIZE = 30;

    private const SEARCH_LIMIT = 10;

    public function search(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $results = SearchableTitle::selectRaw("title_id, ts_rank(search_vector, plainto_tsquery('english',(?))) as rank", [$q])
            ->whereRaw('search_vector @@ plainto_tsquery(?)', [$q])
            ->with(['title' => fn ($query) => $query->with(Title::detailRelations())])
            ->orderByDesc('rank')
            ->take(30)->get()->pluck('title')->filter()->values();

        $hasMore = $results->count() > self::SEARCH_LIMIT;

        return response()->json([
            'hasMore' => $hasMore,
            'results' => TitleResource::collection($results->take(self::SEARCH_LIMIT)),
        ]);
    }

    public function searchAll(Request $request): Response
    {
        $hasParams = $request->hasAny(['q', 'lang', 'gen', 'tag']);

        $results = collect([]);
        if ($hasParams) {
            $q = $request->query('q');

            $results = $q ? SearchableTitle::selectRaw("title_id, ts_rank(search_vector, plainto_tsquery('english',(?))) as rank", [$q])
                ->whereRaw('search_vector @@ plainto_tsquery(?)', [$q])
                ->with('title')
                ->orderByDesc('rank')
                ->take(100)->pluck('title_id') : collect();

            $results = TitleResource::collection(
                $this->applyFilters($results, $request->query('lang'), $request->query('gen'),
                    $request->query('tag'))->paginate(self::PAGINATE_SIZE)
            );
        }

        return Inertia::render('TitleSearchAll', [
            'genres' => Genre::orderByDesc('count')->get(),
            'languages' => Language::orderByDesc('count')->get(),
            'tags' => Keyword::where('count', '>', 100)->orderByDesc('count')->get(),
            'titles' => $results,
        ]);
    }

    private function applyFilters(Collection $titleIds, ?string $lang, ?string $gen, ?string $tag): Builder
    {
        $query = Title::query()->with(Title::detailRelations());
        $languageIds = $lang ? Language::whereCode($lang)->pluck('id') : [];

        if ($languageIds) {
            $query->whereIn('language_id', $languageIds);
        }

        $genreIds = $gen ? Genre::whereSlug($gen)->pluck('id') : [];
        if ($genreIds) {
            $query->whereHas('genres', function ($q) use ($genreIds) {
                $q->whereIn('genres.id', $genreIds);
            });
        }

        $keywordIds = $tag ? Keyword::whereSlug($tag)->pluck('id') : [];

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
