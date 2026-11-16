<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RequireVerifiedEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_is_sent_to_the_verification_notice_instead_of_settings(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/settings')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_cannot_record_title_events(): void
    {
        Bus::fake();
        $title = Title::factory()->create();

        $this->actingAs(User::factory()->unverified()->create())
            ->postJson('/user/title/event', ['title_id' => $title->id, 'type' => 'like'])
            ->assertForbidden();
    }

    public function test_unverified_user_can_still_reach_the_verification_notice(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/email/verify')
            ->assertOk();
    }
}
