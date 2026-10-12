<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum UserTitleList: string
{
    use EnumValues;
    case LIKED = 'liked';
    case WATCHED = 'watched';
    case TO_WATCH = 'to_watch';

    public function toName(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    public function toUrl(): string
    {
        return route('user.show-title-list', $this->value);
    }
}
