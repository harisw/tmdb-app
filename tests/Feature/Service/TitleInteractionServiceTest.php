<?php

namespace Tests\Feature\Service;

use App\Data\DTOs\UserTitleEventData;
use App\Domain\Enums\TitleEventType;
use App\Models\Title;
use App\Models\TitleInteractionEvent;
use App\Models\User;
use App\Models\UserTitleState;
use App\Services\TitleInteractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TitleInteractionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TitleInteractionService $service;

    private User $user;

    private Title $title;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TitleInteractionService::class);
        $this->user = User::factory()->create();
        $this->title = Title::factory()->create();
    }

    private function dto(TitleEventType $type, ?float $value = null): UserTitleEventData
    {
        return UserTitleEventData::from([
            'title_id' => $this->title->id,
            'type' => $type->value,
            'value' => $value,
        ])->setUser($this->user);
    }

    /**
     * Unsaved so the model observer does not run — these cases exercise
     * createOrUpdateState() in isolation from the create() event chain.
     */
    private function unsavedEvent(TitleEventType $type, ?float $value = null): TitleInteractionEvent
    {
        return new TitleInteractionEvent([
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'event_type' => $type,
            'value' => $value,
        ]);
    }

    public function test_create_event_persists_the_event_for_the_dto_user(): void
    {
        $event = $this->service->createEvent($this->dto(TitleEventType::WATCHED));

        $this->assertInstanceOf(TitleInteractionEvent::class, $event);
        $this->assertDatabaseHas('title_interaction_events', [
            'id' => $event->id,
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'event_type' => TitleEventType::WATCHED->value,
        ]);
    }

    public function test_create_event_maps_the_dto_type_onto_the_event_type_column(): void
    {
        $event = $this->service->createEvent($this->dto(TitleEventType::LIKE, 4.5));

        $this->assertSame(TitleEventType::LIKE, $event->refresh()->event_type);
        $this->assertSame('4.5', (string) $event->value);
    }

    public function test_create_event_stores_a_null_value_for_toggle_events(): void
    {
        $event = $this->service->createEvent($this->dto(TitleEventType::TO_WATCH_ADDED));

        $this->assertNull($event->refresh()->value);
    }

    public function test_record_creates_the_event_and_returns_the_synced_state(): void
    {
        $state = $this->service->record($this->dto(TitleEventType::TO_WATCH_ADDED));

        $this->assertInstanceOf(UserTitleState::class, $state);
        $this->assertTrue($state->to_watch);
        $this->assertDatabaseHas('title_interaction_events', [
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'event_type' => TitleEventType::TO_WATCH_ADDED->value,
        ]);
    }

    public function test_like_event_sets_liked_and_stores_an_optional_rating(): void
    {
        $this->service->createOrUpdateState($this->unsavedEvent(TitleEventType::LIKE, 4.5));

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'liked' => true,
            'rate' => 4.5,
        ]);
    }

    public function test_like_event_without_a_value_leaves_the_rate_untouched(): void
    {
        UserTitleState::factory()->create([
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'rate' => 3,
        ]);

        $this->service->createOrUpdateState($this->unsavedEvent(TitleEventType::LIKE));

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'liked' => true,
            'rate' => 3,
        ]);
    }

    public function test_unlike_event_clears_the_liked_flag_only(): void
    {
        UserTitleState::factory()->create([
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'liked' => true,
            'rate' => 4,
        ]);

        $this->service->createOrUpdateState($this->unsavedEvent(TitleEventType::UNLIKE));

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'liked' => false,
            'rate' => 4,
        ]);
    }

    public function test_to_watch_added_event_flags_the_state_for_the_watchlist(): void
    {
        $this->service->createOrUpdateState($this->unsavedEvent(TitleEventType::TO_WATCH_ADDED));

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'to_watch' => true,
        ]);
    }

    public function test_to_watch_removed_event_clears_the_watchlist_flag(): void
    {
        UserTitleState::factory()->create([
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'to_watch' => true,
        ]);

        $this->service->createOrUpdateState($this->unsavedEvent(TitleEventType::TO_WATCH_REMOVED));

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'to_watch' => false,
        ]);
    }

    public function test_watched_event_marks_the_state_watched(): void
    {
        $this->service->createOrUpdateState($this->unsavedEvent(TitleEventType::WATCHED));

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'watched' => true,
        ]);
    }

    public function test_watched_event_carrying_a_value_also_stores_it_as_the_rate(): void
    {
        $this->service->createOrUpdateState($this->unsavedEvent(TitleEventType::WATCHED, 4.5));

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'watched' => true,
            'rate' => 4.5,
        ]);
    }

    public function test_watched_removed_event_clears_the_watched_flag_only(): void
    {
        UserTitleState::factory()->create([
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'watched' => true,
            'rate' => 4,
        ]);

        $this->service->createOrUpdateState($this->unsavedEvent(TitleEventType::WATCHED_REMOVED));

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'watched' => false,
            'rate' => 4,
        ]);
    }

    public function test_a_later_event_updates_the_single_state_row_for_the_pair(): void
    {
        $this->service->createOrUpdateState($this->unsavedEvent(TitleEventType::TO_WATCH_ADDED));
        $this->service->createOrUpdateState($this->unsavedEvent(TitleEventType::WATCHED, 3.5));

        $states = UserTitleState::query()
            ->where('user_id', $this->user->id)
            ->where('title_id', $this->title->id)
            ->get();

        $this->assertCount(1, $states);
        $this->assertTrue($states->first()->to_watch);
        $this->assertTrue($states->first()->watched);
        $this->assertSame('3.5', (string) $states->first()->rate);
    }
}
