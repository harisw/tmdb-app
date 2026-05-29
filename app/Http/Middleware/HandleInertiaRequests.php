<?php

namespace App\Http\Middleware;

use App\Models\Genre;
use App\Models\Provider;
use App\Models\Title;
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
            'genres' => Cache::remember('top-genres', 7200, static function () {
                return Genre::orderByDesc('count')->take(7)->get();
            }),
            'providers' => Cache::remember('top-providers', 7200, static function () {
                return Provider::orderByDesc('count')->take(7)->get();
            }),
            'img' => [
                'poster' => config('services.tmdb.img_url').Title::IMG_SMALL_URL,
                'backdrop' => config('services.tmdb.img_url').Title::IMG_LARGE_URL,
                'logo' => config('services.tmdb.img_url').'original',
            ],
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
