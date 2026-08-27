<?php

namespace App\Services;

use App\Data\DTOs\UserTitleEventData;
use App\Models\TitleInteractionEvent;

class UserTitleService
{
    public function __construct() {}

    public function createEvent(UserTitleEventData $data): ?TitleInteractionEvent
    {
        return TitleInteractionEvent::create([
            'user_id' => $data->getUser()->id,
            ...$data->toArray(),
        ]);
    }
}
