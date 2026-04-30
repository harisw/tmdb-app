<?php

namespace App\Domain\Enums;

enum StreamType: string
{
    case RENT = 'rent';
    case BUY = 'buy';
    case FLATRATE = 'flatrate';
}
