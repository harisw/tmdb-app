<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_throttled_after_six_attempts_a_minute(): void
    {
        foreach (range(1, 6) as $attempt) {
            $this->post('/register', [])->assertSessionHasErrors('email');
        }

        $this->post('/register', [])->assertTooManyRequests();
    }

    public function test_verifying_the_email_sends_the_user_to_onboarding(): void
    {
        $user = User::factory()->unverified()->create();
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($link)->assertRedirect(route('onboarding'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_emailed_verification_link_works_until_the_configured_expiry(): void
    {
        $user = User::factory()->unverified()->create();
        $link = (new VerifyEmail)->toMail($user)->viewData['url'];

        $this->travel(config('auth.verification.expire') - 1)->minutes();

        $this->actingAs($user)->get($link)->assertRedirect(route('onboarding'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_emailed_verification_link_is_refused_after_the_configured_expiry(): void
    {
        $user = User::factory()->unverified()->create();
        $link = (new VerifyEmail)->toMail($user)->viewData['url'];

        $this->travel(config('auth.verification.expire') + 1)->minutes();

        $this->actingAs($user)->get($link)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_link_with_a_tampered_expiry_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $link = (new VerifyEmail)->toMail($user)->viewData['url'];
        $extended = preg_replace('/expires=\d+/', 'expires='.now()->addYear()->timestamp, $link);

        $this->actingAs($user)->get($extended)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
