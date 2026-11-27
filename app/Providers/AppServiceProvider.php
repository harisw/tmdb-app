<?php

namespace App\Providers;

use App\Repositories\Contracts\PersonRepository;
use App\Repositories\Contracts\TitlePersonRepository;
use App\Repositories\EloquentPersonRepository;
use App\Repositories\EloquentTitlePersonRepository;
use App\Services\Database\QueryCounter;
use App\Services\ProviderServices\ProviderCatalog;
use App\Services\ProviderServices\StreamingAvailabilityService;
use App\Services\ProviderServices\WatchmodeService;
use App\Services\TitleService;
use App\Services\TmdbApiService;
use App\Utils\Enum\FeatureFlag;
use Gate;
use GuzzleHttp\Utils;
use Http;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
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

        $this->app->singleton(QueryCounter::class);

        $this->app->scoped(ProviderCatalog::class, fn () => new ProviderCatalog);

        $this->app->scoped(StreamingAvailabilityService::class, fn ($app) => new StreamingAvailabilityService(
            apiKey: config('services.providers.movie_night.api_key'),
            baseUrl: config('services.providers.movie_night.base_url'),
            catalog: $app->make(ProviderCatalog::class),
        ));

        $this->app->scoped(WatchmodeService::class, fn ($app) => new WatchmodeService(
            apiKey: config('services.providers.watchmode.api_key'),
            baseUrl: config('services.providers.watchmode.base_url'),
            catalog: $app->make(ProviderCatalog::class),
        ));

        $this->app->singleton('http.keep-alive-handler', fn () => Utils::chooseHandler());
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

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url): MailMessage => (new MailMessage)
            ->subject('Confirm your email for Chill')
            ->view(['html' => 'mail.verify-email', 'text' => 'mail.verify-email-text'], [
                'url' => $url,
                'email' => $notifiable->getEmailForVerification(),
                'expiresInMinutes' => config('auth.verification.expire', 30),
            ]));

        ResetPassword::toMailUsing(fn (object $notifiable, string $token): MailMessage => (new MailMessage)
            ->subject('Reset your Chill password')
            ->view(['html' => 'mail.reset-password', 'text' => 'mail.reset-password-text'], [
                'url' => rtrim(config('app.url'), '/').route('password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ], absolute: false),
                'email' => $notifiable->getEmailForPasswordReset(),
                'expiresInMinutes' => config('auth.passwords.users.expire'),
            ]));

        Gate::define('admin', fn ($user) => (bool) $user->is_admin);
        Gate::define('viewPulse', fn ($user) => Gate::forUser($user)->allows('admin'));

        Http::macro('keepAlive', fn () => Http::setHandler(app('http.keep-alive-handler')));
        // Defaults only — Pennant stops consulting these once the flag has a
        // stored value for the 'global' scope. @see FeatureFlag::default()
        foreach (FeatureFlag::cases() as $flag) {
            Feature::define($flag->value, fn () => $flag->default());
        }

        RateLimiter::for('tmdb', fn () => Limit::perSecond(10));

        Queue::before(fn (JobProcessing $event) => Context::add('job', [
            'id' => $event->job->uuid(),
            'name' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'attempt' => $event->job->attempts(),
        ]));

        Event::listen(QueryExecuted::class, fn (QueryExecuted $query) => $this->app->make(QueryCounter::class)->record($query->time));
    }
}
