<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
use App\Models\Keyword;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KeywordController extends Controller
{
    public function index(Request $request): Response
    {
        $keyQuery = $request->query('tag');
        $tagOffset = $request->query('tagOffset', 0);
        $key = $keyQuery ? Keyword::whereSlug($keyQuery)->first() : null;

        $tags = Keyword::where('count', '>', 0)->orderByDesc('count')->offset($tagOffset)
            ->take($tagOffset ? 35 : 50)
            ->get();

        return Inertia::render('AllKeywords', [
            'tags' => $tags,
            'selected' => $key,
            'titles' => $key
                ? TitleResource::collection(
                    $key->titles()->listing()->paginate(self::PAGINATE_SIZE)
                )
                : collect(),
        ]);
    }

    public function show(Keyword $keyword): Response
    {
        return Inertia::render('TitleByKeyword', [
            'keyword' => $keyword,
            'titles' => TitleResource::collection(
                $keyword->titles()->listing()->paginate(self::PAGINATE_SIZE)
            ),
        ]);
    }
}
