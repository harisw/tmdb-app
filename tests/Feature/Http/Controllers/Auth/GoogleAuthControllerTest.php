<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Data\DTOs\GoogleUserData;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(string $email, bool $emailVerified = true, string $id = 'google-123'): void
    {
        Socialite::fake('google', GoogleUser::fake([
            'id' => $id,
            'name' => 'Google Person',
            'email' => $email,
            'email_verified' => $emailVerified,
        ]));
    }

    public function test_new_google_user_is_created_with_a_verified_email(): void
    {
        $this->fakeGoogle('newcomer@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/');

        $user = User::where('email', 'newcomer@example.com')->sole();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_account_with_unverified_email_is_refused(): void
    {
        $this->fakeGoogle('unproven@example.com', emailVerified: false);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unproven@example.com']);
    }

    public function test_google_account_with_unverified_email_is_not_linked_to_an_existing_account(): void
    {
        $existing = User::factory()->create(['email' => 'owner@example.com']);
        $this->fakeGoogle('owner@example.com', emailVerified: false);

        $this->get('/auth/google/callback');

        $this->assertNull($existing->fresh()->google_id);
        $this->assertGuest();
    }

    public function test_linking_a_verified_account_keeps_its_password(): void
    {
        $existing = User::factory()->create(['email' => 'owner@example.com']);
        $this->fakeGoogle('owner@example.com');

        $this->get('/auth/google/callback');

        $existing->refresh();
        $this->assertSame('google-123', $existing->google_id);
        $this->assertTrue(Hash::check('password', $existing->password));
        $this->assertAuthenticatedAs($existing);
    }

    public function test_linking_an_unverified_account_discards_the_password_set_by_whoever_registered_it(): void
    {
        $squatted = User::factory()->unverified()->create(['email' => 'victim@example.com']);
        $this->fakeGoogle('victim@example.com');

        $this->get('/auth/google/callback');

        $squatted->refresh();
        $this->assertSame('google-123', $squatted->google_id);
        $this->assertTrue($squatted->hasVerifiedEmail());
        $this->assertFalse(Hash::check('password', $squatted->password));
    }

    public function test_sessions_opened_before_an_unverified_account_was_linked_are_logged_out(): void
    {
        $squatted = User::factory()->unverified()->create(['email' => 'victim@example.com']);
        $this->actingAs($squatted)->get('/login');

        app(UserService::class)->linkGoogleAccount($squatted->fresh(), new GoogleUserData(
            name: 'Google Person',
            email: 'victim@example.com',
            google_id: 'google-123',
            google_token: 'token',
        ));

        $this->actingAs($squatted->fresh());
        $this->get('/settings')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_returning_google_user_whose_email_was_never_marked_verified_is_verified_on_login(): void
    {
        $returning = User::factory()->unverified()->create([
            'email' => 'returning@example.com',
            'google_id' => 'google-123',
            'password' => null,
        ]);
        $this->fakeGoogle('returning@example.com');

        $this->get('/auth/google/callback');

        $this->assertTrue($returning->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($returning);
    }

    public function test_a_different_google_email_does_not_verify_the_stored_one(): void
    {
        $returning = User::factory()->unverified()->create([
            'email' => 'old@example.com',
            'google_id' => 'google-123',
        ]);
        $this->fakeGoogle('new@example.com');

        $this->get('/auth/google/callback');

        $returning->refresh();
        $this->assertFalse($returning->hasVerifiedEmail());
        $this->assertTrue(Hash::check('password', $returning->password));
    }
}
