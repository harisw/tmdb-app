<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum TitleEventType: string
{
    use EnumValues;

    case RATE = 'rate';
    case WATCHED = 'watched';
    case TO_WATCH_ADDED = 'to_watch_added';
    case TO_WATCH_REMOVED = 'to_watch_removed';
}
