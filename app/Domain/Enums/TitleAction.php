<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum TitleAction: string
{
    use EnumValues;
    case LIKE = 'like';
    case WATCHLIST = 'watchlist';
    case WATCHED = 'watched';

    /**
     * @return array{on: TitleEventType, off: TitleEventType}
     */
    public function events(): array
    {
        return match ($this) {
            self::LIKE => ['on' => TitleEventType::LIKE, 'off' => TitleEventType::UNLIKE],
            self::WATCHLIST => ['on' => TitleEventType::TO_WATCH_ADDED, 'off' => TitleEventType::TO_WATCH_REMOVED],
            self::WATCHED => ['on' => TitleEventType::WATCHED, 'off' => TitleEventType::WATCHED_REMOVED],
        };
    }

    public function ratable(): bool
    {
        return in_array($this, [self::LIKE, self::WATCHED], true);
    }
}
