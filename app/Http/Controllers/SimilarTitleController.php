<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
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
            ->with(Title::detailRelations())
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'titles' => TitleResource::collection($titles),
        ]);
    }
}
