<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    public function index(): Response
    {
        $providers = Provider::with(['titles' => function ($query) {
            $query->select('titles.*')
                ->groupBy(['titles.id', 'title_providers.title_id', 'title_providers.provider_id'])
                ->listing()
                ->paginate(self::PAGINATE_SIZE);
        }])->orderByDesc('count')->get()->map(fn (Provider $provider) => array_merge($provider->toArray(), [
            'titles' => TitleResource::collection($provider->titles)->resolve(),
        ]));

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
            'titles' => TitleResource::collection(
                Title::whereIn('id', $titleIds)->listing()->paginate(self::PAGINATE_SIZE)
            ),
        ]);
    }
}
