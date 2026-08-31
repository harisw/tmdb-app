<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
use App\Models\Language;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends Controller
{
    public function index(Request $request): Response
    {
        $lang = $request->query('lang');
        $language = $lang ? Language::whereCode($lang)->first() : null;

        return Inertia::render('TitleByLanguage', [
            'languages' => Language::whereHas('titles')
                ->withCount('titles')
                ->orderByDesc('titles_count')
                ->get(),
            'selected' => $language,
            'titles' => $language
                ? TitleResource::collection(
                    $language->titles()->listing()->paginate(self::PAGINATE_SIZE)
                )
                : collect(),
        ]);
    }
}
