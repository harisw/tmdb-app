<?php

namespace App\Utils\Enum;

use App\Utils\Traits\EnumValues;

enum TimeWindow: string
{
    use EnumValues;
    case DAY = 'day';
    case WEEK = 'week';
}
