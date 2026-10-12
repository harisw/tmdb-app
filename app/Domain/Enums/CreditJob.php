<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum CreditJob: string
{
    use EnumValues;

    case CAST = 'cast';
    case DIRECTOR = 'director';
    case PRODUCER = 'producer';

    case COMPOSER = 'composer';
    case WRITER = 'writer';
    case ART_DIRECTOR = 'art_director';

    public static function getCrewJobs(): array
    {
        return [CreditJob::DIRECTOR,
            CreditJob::PRODUCER,
            CreditJob::COMPOSER,
            CreditJob::WRITER,
            CreditJob::ART_DIRECTOR];
    }
}
