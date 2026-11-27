<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetControllerTest extends TestCase
{
    use RefreshDatabase;

    private const string INVALID_LINK_MESSAGE = 'This reset link is invalid or has expired. Request a new one.';

    public function test_reset_link_is_sent_to_an_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_is_told_no_account_exists(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors(['email' => 'No Chill account uses this email.']);

        Notification::assertNothingSent();
    }

    public function test_a_second_request_within_a_minute_is_told_to_wait(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors(['email' => 'We just sent a link to this email. Give it a minute before asking again.']);

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_link_requests_are_throttled_after_six_a_minute(): void
    {
        foreach (range(1, 6) as $attempt) {
            $this->post(route('password.email'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
        }

        $this->post(route('password.email'), ['email' => 'not-an-email'])->assertTooManyRequests();
    }

    public function test_reset_page_renders_with_token_and_email(): void
    {
        $this->get(route('password.reset', ['token' => 'abc123', 'email' => 'viewer@example.com']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', 'abc123')
                ->where('email', 'viewer@example.com'));
    }

    public function test_valid_token_resets_the_password(): void
    {
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertRedirect(route('login'))->assertSessionHas('message');

        $user->refresh();
        $this->assertTrue(Hash::check('a-brand-new-passphrase', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event) => $event->user->is($user));
        $this->assertGuest();
    }

    public function test_a_token_works_only_once(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ];
        $this->post(route('password.update'), $payload);

        $this->post(route('password.update'), [...$payload, 'password' => 'another-passphrase', 'password_confirmation' => 'another-passphrase'])
            ->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);

        $this->assertTrue(Hash::check('a-brand-new-passphrase', $user->fresh()->password));
    }

    public function test_wrong_token_is_rejected(): void
    {
        $user = User::factory()->create();
        Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => 'not-the-token',
            'email' => $user->email,
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertSessionHasErrors(['email' => self::INVALID_LINK_MESSAGE]);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');
    }

    public function test_signed_in_users_are_sent_away_from_the_reset_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('password.email'), ['email' => $user->email])->assertRedirect('/');
        $this->actingAs($user)->get(route('password.reset', ['token' => 'abc123']))->assertRedirect('/');
    }
}
