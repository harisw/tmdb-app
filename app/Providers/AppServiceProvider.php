<?php

namespace App\Providers;

use App\Services\TitleService;
use App\Services\TmdbService;
use Gate;
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
        $this->app->singleton(TmdbService::class, function () {
            return new TmdbService(
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

        Gate::define('viewPulse', function ($user) {
            return $user->username === env('ADMIN_USERNAME');
        });
    }
}
