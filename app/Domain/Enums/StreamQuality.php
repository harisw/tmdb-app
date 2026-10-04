<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum StreamQuality: string
{
    use EnumValues;

    case SD = 'SD';
    case HD = 'HD';
    case FOUR_K = '4k';

    public static function mapString(string $value): ?string
    {
        $normalized = strtoupper($value);

        return match ($normalized) {
            self::SD->value, self::HD->value => $normalized,
            '4K', 'UHD' => self::FOUR_K->value,
            default => null,
        };
    }
}
