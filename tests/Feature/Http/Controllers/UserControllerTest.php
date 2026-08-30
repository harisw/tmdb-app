<?php

namespace Tests\Feature\Http\Controllers;

use App\Domain\Enums\TitleEventType;
use App\Models\Title;
use App\Models\User;
use App\Models\UserTitleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function postEvent(array $payload): TestResponse
    {
        return $this->postJson(route('user.post-title-event'), $payload);
    }

    public function test_guests_cannot_post_title_events(): void
    {
        $title = Title::factory()->create();

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::WATCHED->value,
            'value' => null,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('title_interaction_events', 0);
    }

    public function test_it_rejects_a_title_id_that_does_not_exist(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postEvent([
            'title_id' => 999999,
            'type' => TitleEventType::WATCHED->value,
            'value' => null,
        ])->assertJsonValidationErrorFor('title_id');
    }

    public function test_it_rejects_an_unknown_event_type(): void
    {
        $this->actingAs(User::factory()->create());
        $title = Title::factory()->create();

        $this->postEvent([
            'title_id' => $title->id,
            'type' => 'bookmarked',
            'value' => null,
        ])->assertJsonValidationErrorFor('type');
    }

    public function test_it_rejects_a_rating_outside_the_zero_to_five_range(): void
    {
        $this->actingAs(User::factory()->create());
        $title = Title::factory()->create();

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::WATCHED->value,
            'value' => 9,
        ])->assertJsonValidationErrorFor('value');
    }

    public function test_it_records_a_like_with_a_rating_and_returns_the_state(): void
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();
        $this->actingAs($user);

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::LIKE->value,
            'value' => 4.5,
        ])
            ->assertOk()
            ->assertJsonPath('data.liked', true)
            ->assertJsonPath('data.rate', 4.5);

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $user->id,
            'title_id' => $title->id,
            'liked' => true,
        ]);
    }

    public function test_it_records_a_like_without_a_rating(): void
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();
        $this->actingAs($user);

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::LIKE->value,
            'value' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.liked', true)
            ->assertJsonPath('data.rate', null);
    }

    public function test_it_records_a_watchlist_event(): void
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();
        $this->actingAs($user);

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::TO_WATCH_ADDED->value,
            'value' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.to_watch', true);

        $this->assertDatabaseHas('title_interaction_events', [
            'user_id' => $user->id,
            'title_id' => $title->id,
            'event_type' => TitleEventType::TO_WATCH_ADDED->value,
            'value' => null,
        ]);
    }

    public function test_watched_removed_toggles_the_state_off(): void
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();
        UserTitleState::factory()->create([
            'user_id' => $user->id,
            'title_id' => $title->id,
            'watched' => true,
        ]);
        $this->actingAs($user);

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::WATCHED_REMOVED->value,
            'value' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.watched', false);
    }

    public function test_the_event_is_attributed_to_the_authenticated_user(): void
    {
        $actor = User::factory()->create();
        $someoneElse = User::factory()->create();
        $title = Title::factory()->create();
        $this->actingAs($actor);

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::WATCHED->value,
            'value' => null,
            'user_id' => $someoneElse->id,
        ])->assertOk();

        $this->assertDatabaseHas('title_interaction_events', [
            'title_id' => $title->id,
            'user_id' => $actor->id,
        ]);
        $this->assertDatabaseMissing('title_interaction_events', [
            'user_id' => $someoneElse->id,
        ]);
    }

    public function test_posting_an_event_updates_the_user_title_state(): void
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();
        $this->actingAs($user);

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::TO_WATCH_ADDED->value,
            'value' => null,
        ])->assertOk();

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $user->id,
            'title_id' => $title->id,
            'to_watch' => true,
        ]);
    }
}
