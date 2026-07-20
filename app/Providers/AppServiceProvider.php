<?php

namespace App\Providers;

use App\Models\Observers\TitleObserver;
use App\Models\Title;
use App\Repositories\Contracts\PersonRepository;
use App\Repositories\Contracts\TitlePersonRepository;
use App\Repositories\EloquentPersonRepository;
use App\Repositories\EloquentTitlePersonRepository;
use App\Services\TitleService;
use App\Services\TmdbApiService;
use Gate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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

        Title::observe(TitleObserver::class);
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

        Gate::define('viewPulse', function ($user) {
            return $user->username === env('ADMIN_USERNAME');
        });

        RateLimiter::for('tmdb', fn () => Limit::perSecond(10));
    }
}
