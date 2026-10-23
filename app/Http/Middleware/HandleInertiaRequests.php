<?php

namespace App\Http\Middleware;

use App\Data\DTOs\UserData;
use App\Domain\Enums\TitleAction;
use App\Domain\Enums\TitleType;
use App\Models\Country;
use App\Models\Genre;
use App\Models\Provider;
use App\Models\Title;
use App\Services\AvatarService;
use Cache;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'topGenres' => Cache::remember('top-genres', 7200, static function () {
                return Genre::orderByDesc('count')->take(7)->get()->toArray();
            }),
            'countries' => Cache::rememberForever('countries', static function () {
                return Country::orderBy('name')->get()->toArray();
            }),
            'topProviders' => Cache::remember('top-providers', 7200, static function () {
                return Provider::orderByDesc('count')->take(7)->get()->toArray();
            }),
            'base_url' => [
                'poster' => config('services.tmdb.img_url').Title::IMG_SMALL_URL,
                'backdrop' => config('services.tmdb.img_url').Title::IMG_LARGE_URL,
                'logo' => config('services.tmdb.img_url').'original',
                'profile' => config('services.avatar.base_url'),
            ],
            'auth' => [
                'user' => fn () => $request->user()
                    ? UserData::fromModel($request->user()->loadMissing('country'))
                    : null,
            ],
            'enums' => [
                'TitleType' => TitleType::collection(),
                'TitleAction' => TitleAction::collection()->mapWithKeys(fn (TitleAction $action) => [
                    $action->value => [
                        'on' => $action->events()['on']->value,
                        'off' => $action->events()['off']->value,
                        'ratable' => $action->ratable(),
                    ],
                ]),
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'avatarStyles' => Cache::rememberForever('avatar-styles', fn () => AvatarService::getStyles()),
        ]);
    }
}
