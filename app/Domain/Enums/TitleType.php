<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum TitleType: string
{
    use EnumValues;

    case MOVIE = 'movie';       // Displayed as Film
    case TV = 'tv';
}
