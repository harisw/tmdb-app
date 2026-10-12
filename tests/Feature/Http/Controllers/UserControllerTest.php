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
            'value' => 13,
        ])->assertJsonValidationErrorFor('value');
    }

    public function test_it_records_an_event_and_returns_the_synced_state(): void
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();
        $this->actingAs($user);

        $this->postEvent([
            'title_id' => $title->id,
            'type' => TitleEventType::LIKE->value,
            'value' => 4.5,
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.liked', true)
            ->assertJsonPath('data.rate', 4.5);

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $user->id,
            'title_id' => $title->id,
            'liked' => true,
        ]);
    }

    public function test_guests_cannot_view_their_title_lists(): void
    {
        $this->get(route('user.title-lists'))->assertRedirect(route('login'));
    }

    public function test_title_lists_groups_titles_by_pivot_state_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $liked = Title::factory()->create();
        $watched = Title::factory()->create();
        $someoneElsesLike = Title::factory()->create();

        UserTitleState::factory()->create(['user_id' => $user->id, 'title_id' => $liked->id, 'liked' => true]);
        UserTitleState::factory()->create(['user_id' => $user->id, 'title_id' => $watched->id, 'watched' => true]);
        UserTitleState::factory()->create(['title_id' => $someoneElsesLike->id, 'liked' => true]);

        $this->actingAs($user)
            ->get(route('user.title-lists'))
            ->assertInertia(fn ($page) => $page
                ->component('AllUserLists')
                ->has('lists', 3)
                ->where('lists.0.value', 'liked')
                ->where('lists.0.titles', fn ($titles) => collect($titles)->pluck('id')->all() === [$liked->id])
                ->where('lists.1.value', 'watched')
                ->where('lists.1.titles', fn ($titles) => collect($titles)->pluck('id')->all() === [$watched->id])
            );
    }

    public function test_show_title_list_paginates_a_single_list(): void
    {
        $user = User::factory()->create();
        $inList = Title::factory()->create();
        $notInList = Title::factory()->create();

        UserTitleState::factory()->create(['user_id' => $user->id, 'title_id' => $inList->id, 'to_watch' => true]);
        UserTitleState::factory()->create(['user_id' => $user->id, 'title_id' => $notInList->id, 'watched' => true]);

        $this->actingAs($user)
            ->get(route('user.show-title-list', 'to_watch'))
            ->assertInertia(fn ($page) => $page
                ->component('TitleByUserList')
                ->where('list.value', 'to_watch')
                ->has('titles.data', 1)
                ->where('titles.data.0.id', $inList->id)
            );
    }

    public function test_show_title_list_rejects_an_unknown_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('user.show-title-list', 'bookmarked'))
            ->assertNotFound();
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
        ])->assertSuccessful();

        $this->assertDatabaseHas('title_interaction_events', [
            'title_id' => $title->id,
            'user_id' => $actor->id,
        ]);
        $this->assertDatabaseMissing('title_interaction_events', [
            'user_id' => $someoneElse->id,
        ]);
    }
}
