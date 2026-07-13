<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum CreditJob: string
{
    use EnumValues;

    case CAST = 'cast';
    case DIRECTOR = 'director';
    case COMPOSER = 'composer';
    case WRITER = 'writer';
    case ART_DIRECTOR = 'art_director';
    case PRODUCER = 'producer';
}
