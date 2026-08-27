<?php

namespace App\Services;

use App\Data\DTOs\UserTitleEventData;
use App\Domain\Enums\TitleEventType;
use App\Models\TitleInteractionEvent;
use App\Models\UserTitleState;

class TitleInteractionService
{
    public function __construct() {}

    public function createEvent(UserTitleEventData $data): ?TitleInteractionEvent
    {
        return TitleInteractionEvent::create([
            'user_id' => $data->getUser()->id,
            'title_id' => $data->title_id,
            'event_type' => $data->type,
            'value' => $data->value,
        ]);
    }

    public function createOrUpdateState(TitleInteractionEvent $event): ?UserTitleState
    {
        return UserTitleState::updateOrCreate([
            'user_id' => $event->user_id,
            'title_id' => $event->title_id,
        ], $this->getUpdateAttribute($event));
    }

    private function getUpdateAttribute(TitleInteractionEvent $event): array
    {
        return match ($event->event_type) {
            TitleEventType::RATE => ['rate' => $event->value],
            TitleEventType::TO_WATCH_ADDED => ['to_watch' => true],
            TitleEventType::TO_WATCH_REMOVED => ['to_watch' => false],
            TitleEventType::WATCHED => [
                'watched' => true,
                ...($event->value ? ['rate' => $event->value] : []),
            ]
        };
    }
}
