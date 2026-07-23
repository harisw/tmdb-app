<?php

namespace App\Http\Controllers;

use App\Models\Title;
use App\Models\TitleSimilarity;
use Illuminate\Http\JsonResponse;

class TitleGraphController extends Controller
{
    private const MIN_SCORE = 0.5;

    public function show(Title $title): JsonResponse
    {
        $similarities = TitleSimilarity::with('similarTitle:id,name,poster_path,release_date')
            ->where('title_id', $title->id)
            ->where('score', '>=', self::MIN_SCORE) // threshold — tune this
            ->orderByDesc('score')
            ->limit(15)
            ->get();

        return response()->json([
            'node' => [
                'id' => $title->id,
                'name' => $title->name,
                'poster_path' => $title->poster_path,
                'release_year' => $title->release_date,
            ],
            'edges' => $similarities->map(fn ($s) => [
                'id' => $s->similarTitle->id,
                'name' => $s->similarTitle->name,
                'poster_path' => $s->similarTitle->poster_path,
                'release_year' => $s->similarTitle->release_date,
                'score' => $s->score,
                'reasons' => $s->reasons,
            ]),
        ]);
    }
}
