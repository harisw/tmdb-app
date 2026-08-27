<?php

namespace App\Providers;

use App\Repositories\Contracts\PersonRepository;
use App\Repositories\Contracts\TitlePersonRepository;
use App\Repositories\EloquentPersonRepository;
use App\Repositories\EloquentTitlePersonRepository;
use App\Services\TitleService;
use App\Services\TmdbApiService;
use App\Utils\Enum\FeatureFlag;
use Gate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pulse\Facades\Pulse;
use URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PersonRepository::class, EloquentPersonRepository::class);
        $this->app->bind(TitlePersonRepository::class, EloquentTitlePersonRepository::class);

        $this->app->singleton(TmdbApiService::class, function () {
            return new TmdbApiService(
                apiKey: config('services.tmdb.api_key'),
                baseUrl: config('services.tmdb.base_url')
            );
        });
        $this->app->bind(TitleService::class, function () {
            return new TitleService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Pulse::user(fn ($user) => [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        // Anchored to a column, not to the username: usernames are editable at
        // /profile, so deriving admin from one meant anyone could rename their
        // way into it on any database where the name was still unclaimed.
        Gate::define('admin', fn ($user) => (bool) $user->is_admin);
        Gate::define('viewPulse', fn ($user) => Gate::forUser($user)->allows('admin'));

        // Defaults only — Pennant stops consulting these once the flag has a
        // stored value for the 'global' scope. @see FeatureFlag::default()
        foreach (FeatureFlag::cases() as $flag) {
            Feature::define($flag->value, fn () => $flag->default());
        }

        RateLimiter::for('tmdb', fn () => Limit::perSecond(10));
    }
}
