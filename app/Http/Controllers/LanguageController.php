<?php

namespace App\Http\Controllers;

use App\Models\Language;
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
            'languages' => Language::orderByDesc('count')->whereHas('titles')->get(),
            'titles' => $language ? $language->titles()->with(['genres', 'keywords'])->latest()->paginate(self::PAGINATE_SIZE)
                : null,
        ]);
    }
}
