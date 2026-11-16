<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    private function failLogin(string $email, int $times): void
    {
        foreach (range(1, $times) as $attempt) {
            $this->post('/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }
    }

    public function test_correct_password_logs_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_correct_password_is_refused_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();
        $this->failLogin($user->email, 5);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_four_failed_attempts_do_not_lock_the_account(): void
    {
        $user = User::factory()->create();
        $this->failLogin($user->email, 4);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_login_resets_the_failed_attempt_count(): void
    {
        $user = User::factory()->create();
        $this->failLogin($user->email, 4);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/logout');

        $this->failLogin($user->email, 4);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_lockout_of_one_email_does_not_lock_another(): void
    {
        $locked = User::factory()->create();
        $other = User::factory()->create();
        $this->failLogin($locked->email, 5);

        $this->post('/login', ['email' => $other->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($other);
    }

    public function test_unverified_user_is_logged_in_and_sent_to_the_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($user);
    }
}
