<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class VerifyEmailMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_is_branded_as_chill(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertSame('Confirm your email for Chill', $this->mailFor($user)->subject);
    }

    public function test_html_body_carries_the_chill_brand_the_link_and_the_address(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'viewer@example.com']);

        $html = (string) $this->mailFor($user)->render();

        $this->assertStringContainsString('Chill', $html);
        $this->assertStringContainsString('viewer@example.com', $html);
        $this->assertStringContainsString("/email/verify/{$user->id}/".sha1($user->email), $html);
        $this->assertStringNotContainsStringIgnoringCase('TMDB', $html);
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_plain_text_body_carries_the_link(): void
    {
        $user = User::factory()->unverified()->create();
        $mail = $this->mailFor($user);

        $text = view($mail->view['text'], $mail->viewData)->render();

        $this->assertStringContainsString('Chill', $text);
        $this->assertStringContainsString("/email/verify/{$user->id}/".sha1($user->email), $text);
        $this->assertStringNotContainsString('<', $text);
    }

    public function test_expiry_copy_follows_the_configured_window(): void
    {
        config(['auth.verification.expire' => 30]);
        $user = User::factory()->unverified()->create();

        $this->assertStringContainsString('30 minutes', (string) $this->mailFor($user)->render());
    }

    private function mailFor(User $user): MailMessage
    {
        return (new VerifyEmail)->toMail($user);
    }
}
