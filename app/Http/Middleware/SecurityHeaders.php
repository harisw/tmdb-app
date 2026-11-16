<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const EXTERNAL_IMAGE_SOURCES = ['https://www.svgrepo.com'];

    private const EXTERNAL_CONNECT_SOURCES = ['https://ipapi.co'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $response->headers->add([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! Vite::isRunningHot() && ! $request->is(config('horizon.path'), config('horizon.path').'/*', config('pulse.path'))) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-".Vite::cspNonce()."'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => [
                "'self'",
                'data:',
                $this->origin(config('services.tmdb.img_url')),
                $this->origin(config('services.avatar.base_url')),
                ...self::EXTERNAL_IMAGE_SOURCES,
            ],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'", ...self::EXTERNAL_CONNECT_SOURCES],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', array_filter($sources)))
            ->implode('; ');
    }

    private function origin(?string $url): ?string
    {
        $parts = parse_url((string) $url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
