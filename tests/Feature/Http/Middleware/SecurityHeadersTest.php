<?php

namespace Tests\Feature\Http\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, list<string>>
     */
    private function cspDirectives(TestResponse $response): array
    {
        return collect(explode(';', $response->headers->get('Content-Security-Policy')))
            ->map(fn (string $directive) => preg_split('/\s+/', trim($directive)))
            ->mapWithKeys(fn (array $parts) => [array_shift($parts) => $parts])
            ->all();
    }

    public function test_pages_only_run_scripts_from_the_app_or_carrying_the_request_nonce(): void
    {
        $response = $this->get('/login')->assertOk();

        $csp = $this->cspDirectives($response);
        $this->assertSame(["'self'", "'nonce-".Vite::cspNonce()."'"], $csp['script-src']);
        $this->assertSame(["'none'"], $csp['object-src']);
        $this->assertSame(["'self'"], $csp['frame-ancestors']);
        $this->assertSame(["'self'"], $csp['base-uri']);
    }

    public function test_images_are_allowed_from_the_configured_tmdb_and_avatar_hosts(): void
    {
        config([
            'services.tmdb.img_url' => 'https://image.tmdb.org/t/p/',
            'services.avatar.base_url' => 'https://api.dicebear.com/10.x/',
        ]);

        $imgSrc = $this->cspDirectives($this->get('/login'))['img-src'];

        $this->assertContains('https://image.tmdb.org', $imgSrc);
        $this->assertContains('https://api.dicebear.com', $imgSrc);
    }

    public function test_an_unset_image_host_leaves_no_empty_source(): void
    {
        config(['services.tmdb.img_url' => null]);

        $imgSrc = $this->cspDirectives($this->get('/login'))['img-src'];

        $this->assertNotContains('', $imgSrc);
    }

    public function test_responses_carry_the_baseline_headers(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_unmatched_routes_still_get_the_headers(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_hsts_is_sent_only_in_production(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->app['env'] = 'production';

        $this->get('/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_horizon_and_pulse_dashboards_are_left_without_a_csp(): void
    {
        $this->get('/'.config('horizon.path'))
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get('/'.config('pulse.path'))
            ->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_no_csp_is_sent_while_the_vite_dev_server_is_running(): void
    {
        $hotFile = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($hotFile, 'http://[::1]:5173');
        $this->withVite();
        Vite::useHotFile($hotFile);

        try {
            $this->get('/login')->assertHeaderMissing('Content-Security-Policy');
        } finally {
            unlink($hotFile);
        }
    }
}
