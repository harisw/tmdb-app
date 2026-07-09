<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KeywordController extends Controller
{
    private const PAGINATE_SIZE = 30;

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
            'titles' => $key ? $key->titles()->with(['genres', 'keywords'])->latest()->paginate(self::PAGINATE_SIZE)
                : collect(),
        ]);
    }

    public function show(Keyword $keyword): Response
    {
        return Inertia::render('TitleByKeyword', [
            'keyword' => $keyword,
            'titles' => $keyword->titles()->with(['genres', 'keywords', 'titleProviders' => function ($q) {
                $q->with('provider')->uniqueByProvider();
            }])->latest()->paginate(self::PAGINATE_SIZE),
        ]);
    }
}
