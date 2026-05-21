<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum TitleType: string
{
    use EnumValues;

    case MOVIE = 'movie';
    case TV = 'tv';
}
