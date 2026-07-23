<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

/**
 * TMDB encodes gender as an integer on every person node. Storing that raw int
 * would leak their encoding into our schema, and mapping it ad hoc at each call
 * site is how we ended up with three different spellings of "unknown". This is
 * the single mapping.
 */
enum Gender: string
{
    use EnumValues;

    case NOT_SPECIFIED = 'not_specified';
    case FEMALE = 'female';
    case MALE = 'male';
    case NON_BINARY = 'non_binary';

    public static function fromTmdb(?int $value): self
    {
        return match ($value) {
            1 => self::FEMALE,
            2 => self::MALE,
            3 => self::NON_BINARY,
            default => self::NOT_SPECIFIED,
        };
    }
}
