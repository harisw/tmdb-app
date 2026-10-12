<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum StreamType: string
{
    use EnumValues;

    case RENT = 'rent';
    case BUY = 'buy';
    case FLATRATE = 'flatrate';
    case FREE = 'free';
    case ADS = 'ads';
    case ADDON = 'addon';
}
