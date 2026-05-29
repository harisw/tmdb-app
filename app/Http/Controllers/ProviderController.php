<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    private const PAGINATE_SIZE = 30;

    public function index(): Response
    {
        $providers = Provider::with(['titles' => function ($query) {
            $query->select('titles.*')
                ->with(['genres', 'keywords'])
                ->distinct()
                ->orderByDesc('titles.created_at')
                ->take(30);
        }])->orderByDesc('count')->get();

        return Inertia::render('AllProviders', [
            'allProviders' => $providers,
        ]);
    }

    public function show(Provider $provider): Response
    {
        $titleIds = TitleProvider::where('provider_id', $provider->id)
            ->distinct()
            ->pluck('title_id');

        return Inertia::render('TitleByProvider', [
            'provider' => $provider,
            'titles' => Title::whereIn('id', $titleIds)
                ->with(['genres', 'keywords'])
                ->latest()
                ->paginate(self::PAGINATE_SIZE),
        ]);
    }
}
