<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResetPasswordMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_is_branded_as_chill(): void
    {
        $user = User::factory()->create();

        $this->assertSame('Reset your Chill password', $this->mailFor($user, 'tok')->subject);
    }

    public function test_html_body_carries_the_brand_link_and_expiry(): void
    {
        config(['auth.passwords.users.expire' => 45]);
        $user = User::factory()->create(['email' => 'viewer@example.com']);

        $html = (string) $this->mailFor($user, 'secret-token')->render();

        $this->assertStringContainsString('Chill', $html);
        $this->assertStringContainsString('/reset-password/secret-token?email=viewer%40example.com', $html);
        $this->assertStringContainsString('45 minutes', $html);
    }

    public function test_plain_text_body_carries_the_link(): void
    {
        $user = User::factory()->create();
        $mail = $this->mailFor($user, 'secret-token');

        $text = view($mail->view['text'], $mail->viewData)->render();

        $this->assertStringContainsString('/reset-password/secret-token', $text);
        $this->assertStringNotContainsString('<', $text);
    }

    public function test_reset_link_points_at_app_url_even_when_the_host_header_is_forged(): void
    {
        config(['app.url' => 'https://chill.test']);
        Notification::fake();
        $user = User::factory()->create();

        $this->withHeader('Host', 'attacker.example')
            ->post('http://attacker.example/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->viewData['url'];

            return str_starts_with($url, 'https://chill.test/reset-password/');
        });
    }

    private function mailFor(User $user, string $token): MailMessage
    {
        return (new ResetPassword($token))->toMail($user);
    }
}
