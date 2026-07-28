<?php

namespace App\Http\Controllers;

use App\Models\Title;
use Illuminate\Http\JsonResponse;

class SimilarTitleController extends Controller
{
    private const LIMIT = 12;

    public function index(Title $title): JsonResponse
    {
        $titles = $title->similarTitles()
            ->select([
                'titles.id', 'name', 'slug', 'type', 'rating', 'release_date',
                'runtime', 'backdrop_path', 'poster_path', 'overview',
            ])
            ->wherePivot('score', '>=', config('features.knowledge_graph.min_score'))
            ->with(['genres', 'keywords', 'titleProviders' => function ($q) {
                $q->with('provider')->uniqueByProvider();
            }])
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'titles' => $titles,
        ]);
    }
}
