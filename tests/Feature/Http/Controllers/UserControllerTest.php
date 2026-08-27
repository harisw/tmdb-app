<?php

namespace Tests\Feature\Http\Controllers;

use App\Domain\Enums\TitleEventType;
use App\Models\Title;
use App\Models\User;
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

    public function test_it_requires_a_value_for_rate_events(): void
    {
        $this->actingAs(User::factory()->create());
        $title = Title::factory()->create();

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::RATE->value,
        ])->assertJsonValidationErrorFor('value');
    }

    public function test_it_rejects_a_rating_outside_the_zero_to_five_range(): void
    {
        $this->actingAs(User::factory()->create());
        $title = Title::factory()->create();

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::RATE->value,
            'value' => 9,
        ])->assertJsonValidationErrorFor('value');
    }

    public function test_it_records_a_rate_event_and_returns_the_resource(): void
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();
        $this->actingAs($user);

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::RATE->value,
            'value' => 4.5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.title_id', $title->id)
            ->assertJsonPath('data.event_type', TitleEventType::RATE->value)
            ->assertJsonPath('data.value', 4.5);

        $this->assertDatabaseHas('title_interaction_events', [
            'user_id' => $user->id,
            'title_id' => $title->id,
            'event_type' => TitleEventType::RATE->value,
        ]);
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
        ])->assertCreated();

        $this->assertDatabaseHas('title_interaction_events', [
            'user_id' => $user->id,
            'title_id' => $title->id,
            'event_type' => TitleEventType::TO_WATCH_ADDED->value,
            'value' => null,
        ]);
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
        ])->assertCreated();

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
        ])->assertCreated();

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $user->id,
            'title_id' => $title->id,
            'to_watch' => true,
        ]);
    }
}
