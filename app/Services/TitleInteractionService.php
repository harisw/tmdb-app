<?php

namespace App\Services;

use App\Data\DTOs\UserTitleEventData;
use App\Domain\Enums\TitleEventType;
use App\Models\TitleInteractionEvent;
use App\Models\UserTitleState;

class TitleInteractionService
{
    /**
     * Record an interaction: append it to the event log and sync the derived
     * UserTitleState row that the read model serves from.
     */
    public function record(UserTitleEventData $data): UserTitleState
    {
        return $this->createOrUpdateState($this->createEvent($data));
    }

    public function createEvent(UserTitleEventData $data): TitleInteractionEvent
    {
        return TitleInteractionEvent::create([
            'user_id' => $data->getUser()->id,
            'title_id' => $data->title_id,
            'event_type' => $data->type,
            'value' => $data->value,
        ]);
    }

    public function createOrUpdateState(TitleInteractionEvent $event): UserTitleState
    {
        return UserTitleState::updateOrCreate([
            'user_id' => $event->user_id,
            'title_id' => $event->title_id,
        ], $this->getUpdateAttribute($event));
    }

    /**
     * @return array<string, mixed>
     */
    private function getUpdateAttribute(TitleInteractionEvent $event): array
    {
        return match ($event->event_type) {
            TitleEventType::LIKE => [
                'liked' => true,
                ...($event->value !== null ? ['rate' => $event->value] : []),
            ],
            TitleEventType::UNLIKE => ['liked' => false],
            TitleEventType::TO_WATCH_ADDED => ['to_watch' => true],
            TitleEventType::TO_WATCH_REMOVED => ['to_watch' => false],
            TitleEventType::WATCHED => [
                'watched' => true,
                ...($event->value !== null ? ['rate' => $event->value] : []),
            ],
            TitleEventType::WATCHED_REMOVED => ['watched' => false],
        };
    }
}
