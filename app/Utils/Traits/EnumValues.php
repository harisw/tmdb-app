<?php

namespace App\Utils\Traits;

trait EnumValues
{
    public static function values(): array
    {
        return array_map(
            static fn ($case) => $case->value,
            self::cases()
        );
    }
}
