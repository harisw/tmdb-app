<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum CreditRole: string
{
    use EnumValues;

    case CAST = 'cast';
    case CREW = 'crew';
}
