<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
use App\Models\Language;
use App\Models\Title;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends Controller
{
    private const PAGINATE_SIZE = 30;

    public function index(Request $request): Response
    {
        $lang = $request->query('lang');
        $language = $lang ? Language::whereCode($lang)->first() : null;

        return Inertia::render('TitleByLanguage', [
            // Counted live rather than read off languages.count, which only
            // refreshes when CountsSeeder runs and so reads 0 between imports.
            'languages' => Language::whereHas('titles')
                ->withCount('titles')
                ->orderByDesc('titles_count')
                ->get(),
            'selected' => $language,
            'titles' => $language
                ? TitleResource::collection(
                    $language->titles()
                        ->with(Title::detailRelations())
                        ->latest()
                        ->paginate(self::PAGINATE_SIZE)
                )
                : collect(),
        ]);
    }
}
