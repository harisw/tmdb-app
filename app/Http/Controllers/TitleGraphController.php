<?php

namespace App\Http\Controllers;

use App\Models\Scopes\AvoidedKeywordScope;
use App\Models\Title;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TitleGraphController extends Controller
{
    private const LIMIT = 8;

    /**
     * The Inertia explorer page. Keyed by the ?title_slug= query param rather
     * than a path segment so traversal can re-point the same page at whichever
     * title the user drills into (shareable, reflected in the URL).
     */
    public function index(Request $request): Response
    {
        return Inertia::render('TitleGraph', [
            'slug' => $request->query('title_slug'),
        ]);
    }

    /**
     * One level of neighbours for a title, as {node, nodes, links}. Called on
     * initial load and again for each node the user expands.
     */
    public function neighbors(Request $request): JsonResponse
    {
        $request->validate(['title_slug' => ['required', 'string']]);

        $title = Title::withoutGlobalScope(AvoidedKeywordScope::class)
            ->where('slug', $request->query('title_slug'))
            ->firstOrFail();

        $neighbours = $title->similarTitles()
            ->select(['titles.id', 'slug', 'name', 'poster_path', 'release_date', 'rating', 'type'])
            ->wherePivot('score', '>=', config('features.knowledge_graph.min_score'))
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'node' => $title->toGraphNode(),
            'nodes' => $neighbours->map(fn (Title $t) => $t->toGraphNode())->values(),
            'links' => $neighbours->map(fn (Title $t) => [
                'source' => $title->id,
                'target' => $t->id,
                'score' => round($t->pivot->score, 3),
                'reasons' => $t->pivot->reasons,
            ])->values(),
        ]);
    }
}
