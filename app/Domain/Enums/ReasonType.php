<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum ReasonType: string
{
    use EnumValues;

    case SEMANTIC_SIMILARITY = 'semantic_similarity';
    case SHARED_GENRES = 'shared_genres';
    case SHARED_KEYWORDS = 'shared_keywords';
    case SHARED_CASTS = 'shared_casts';
    case SHARED_DIRECTOR = 'shared_director';
    case SHARED_PRODUCER = 'shared_producer';
    case SHARED_COMPOSER = 'shared_composer';
    case SHARED_WRITER = 'shared_writer';
    case SHARED_ART_DIRECTOR = 'shared_art_director';

    public static function sharedCrew(CreditJob $job): self
    {
        return self::from("shared_{$job->value}");
    }
}
