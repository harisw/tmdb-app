<?php

namespace App\Utils\Traits;

use Illuminate\Support\Collection;

trait EnumValues
{
    public static function values(): array
    {
        return array_map(
            static fn ($case) => $case->value,
            self::cases()
        );
    }

    public static function collection(): Collection
    {
        return collect(self::values());
    }
}
