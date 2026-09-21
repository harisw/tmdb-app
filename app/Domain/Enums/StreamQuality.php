<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum StreamQuality: string
{
    use EnumValues;

    case SD = 'SD';
    case HD = 'HD';
    case FOUR_K = '4k';

    private const UHD = 'uhd';

    public static function mapString(string $value): ?string
    {
        $uppered = strtoupper($value);
        if (CreditJob::tryFrom($uppered)) {
            return $uppered;
        }

        return $value === self::UHD ? $uppered : null;
    }
}
