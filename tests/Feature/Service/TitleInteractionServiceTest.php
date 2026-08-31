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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_create_event_persists_the_event_and_maps_the_dto_type_and_value(): void
    {
        $event = $this->service->createEvent($this->dto(TitleEventType::LIKE, 4.5));

        $this->assertSame(TitleEventType::LIKE, $event->refresh()->event_type);
        $this->assertSame('4.5', (string) $event->value);
        $this->assertDatabaseHas('title_interaction_events', [
            'id' => $event->id,
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'event_type' => TitleEventType::LIKE->value,
        ]);
    }

    public function test_record_creates_the_event_and_returns_the_synced_state(): void
    {
        $state = $this->service->record($this->dto(TitleEventType::TO_WATCH_ADDED));

        $this->assertTrue($state->to_watch);
        $this->assertDatabaseHas('title_interaction_events', [
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            'event_type' => TitleEventType::TO_WATCH_ADDED->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $initialState
     * @param  array<string, mixed>  $expectedState
     */
    #[DataProvider('stateSyncProvider')]
    public function test_event_syncs_the_expected_user_title_state(
        TitleEventType $type,
        ?float $value,
        array $initialState,
        array $expectedState,
    ): void {
        if ($initialState !== []) {
            UserTitleState::factory()->create([
                'user_id' => $this->user->id,
                'title_id' => $this->title->id,
                ...$initialState,
            ]);
        }

        $this->service->createOrUpdateState($this->unsavedEvent($type, $value));

        $this->assertDatabaseHas('user_title_states', [
            'user_id' => $this->user->id,
            'title_id' => $this->title->id,
            ...$expectedState,
        ]);
    }

    /**
     * @return array<string, array{0: TitleEventType, 1: float|null, 2: array<string, mixed>, 3: array<string, mixed>}>
     */
    public static function stateSyncProvider(): array
    {
        return [
            'like with a rating sets liked and rate' => [TitleEventType::LIKE, 4.5, [], ['liked' => true, 'rate' => 4.5]],
            'like without a value leaves the rate untouched' => [TitleEventType::LIKE, null, ['rate' => 3], ['liked' => true, 'rate' => 3]],
            'unlike clears the liked flag only' => [TitleEventType::UNLIKE, null, ['liked' => true, 'rate' => 4], ['liked' => false, 'rate' => 4]],
            'to-watch added flags the watchlist' => [TitleEventType::TO_WATCH_ADDED, null, [], ['to_watch' => true]],
            'to-watch removed clears the watchlist flag' => [TitleEventType::TO_WATCH_REMOVED, null, ['to_watch' => true], ['to_watch' => false]],
            'watched marks the state watched' => [TitleEventType::WATCHED, null, [], ['watched' => true]],
            'watched carrying a value also stores it as the rate' => [TitleEventType::WATCHED, 4.5, [], ['watched' => true, 'rate' => 4.5]],
            'watched removed clears the watched flag only' => [TitleEventType::WATCHED_REMOVED, null, ['watched' => true, 'rate' => 4], ['watched' => false, 'rate' => 4]],
        ];
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
