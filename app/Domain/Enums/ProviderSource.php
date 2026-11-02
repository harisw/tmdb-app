<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum ProviderSource: string
{
    use EnumValues;

    case TMDB = 'tmdb';
    case WATCHMODE = 'watchmode';
    case STREAMING_AVAILABILITY = 'streaming_availability';
}
