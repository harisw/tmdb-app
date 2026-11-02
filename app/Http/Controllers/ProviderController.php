<?php

namespace App\Http\Controllers;

use App\Domain\Enums\TitleSort;
use App\Http\Requests\BrowseTitlesRequest;
use App\Http\Resources\TitleResource;
use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    private const ROW_SIZE = 15;

    private const FEATURED_COUNT = 6;

    public function index(): Response
    {
        $providers = Provider::query()
            ->where('count', '>', 0)
            ->orderByDesc('count')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'img_logo', 'count']);

        $featured = $providers->take(self::FEATURED_COUNT)
            ->map(fn (Provider $provider) => [
                ...$provider->toArray(),
                'titles' => TitleResource::collection(
                    $this->titlesOf($provider)->sorted(TitleSort::POPULAR)->limit(self::ROW_SIZE)->get()
                )->resolve(),
            ])
            ->filter(fn (array $provider) => $provider['titles'] !== []);

        return Inertia::render('AllProviders', [
            'allProviders' => $providers,
            'featuredProviders' => $featured->values(),
        ]);
    }

    public function show(BrowseTitlesRequest $request, Provider $provider): Response
    {
        $sort = $request->sort();

        return Inertia::render('TitleByProvider', [
            'provider' => $provider,
            'titles' => TitleResource::collection(
                $this->titlesOf($provider)->sorted($sort)->paginate(self::PAGINATE_SIZE)
            ),
            'sort' => $sort->value,
            'sorts' => TitleSort::options(),
        ]);
    }

    /**
     * @return Builder<Title>
     */
    private function titlesOf(Provider $provider): Builder
    {
        return Title::query()
            ->with(Title::cardRelations())
            ->whereIn('titles.id', TitleProvider::where('provider_id', $provider->id)->select('title_id'));
    }
}
