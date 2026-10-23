<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\Title;
use App\Services\Search\HybridSearchService;
use App\Utils\Enum\FeatureFlag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class SearchController extends Controller
{
    private const SEARCH_LIMIT = 10;

    private const SEARCH_ALL_LIMIT = 100;

    public function __construct(private readonly HybridSearchService $searchService) {}

    public function search(Request $request): JsonResponse
    {
        $q = $request->validate(['q' => ['required', 'string']])['q'];
        $ranks = $this->searchService->hybridIds($q, self::SEARCH_LIMIT + 1)->flip();
        $results = Title::whereIn('id', $ranks->keys())
            ->with(Title::detailRelations())
            ->get()
            ->sortBy(fn (Title $title) => $ranks[$title->id])
            ->values();

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

            $titleIds = $q ? $this->rankedTitleIds($q) : null;

            $results = TitleResource::collection(
                $this->applyFilters($titleIds, $request->query('lang'), $request->query('gen'),
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

    /**
     * @return Collection<int, int>
     */
    private function rankedTitleIds(string $q): Collection
    {
        return Feature::for('global')->active(FeatureFlag::HydeSearch)
            ? $this->searchService->hydeIds($q, self::SEARCH_ALL_LIMIT)
            : $this->searchService->hybridIds($q, self::SEARCH_ALL_LIMIT);
    }

    /**
     * @param  Collection<int, int>|null  $titleIds
     */
    private function applyFilters(?Collection $titleIds, ?string $lang, ?string $gen, ?string $tag): Builder
    {
        $query = Title::listing();
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

        if ($titleIds !== null) {
            $query->whereIn('id', $titleIds)
                ->reorder()
                ->orderByRaw('array_position(?::bigint[], titles.id)', ['{'.$titleIds->implode(',').'}']);
        }

        return $query;
    }
}
