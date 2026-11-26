<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
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
}
