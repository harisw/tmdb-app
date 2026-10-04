<?php

namespace App\Domain\Casts;

use App\Domain\Enums\StreamType;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class ProviderStreamTypeCast implements Cast
{
    private const TYPE_MAP = [
        'sub' => StreamType::FLATRATE,
        'subscription' => StreamType::FLATRATE,
    ];

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if ($value instanceof StreamType) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        if ($enum = StreamType::tryFrom($value)) {
            return $enum;
        }

        return self::TYPE_MAP[$value] ?? null;
    }

    public static function map(): array
    {
        return self::TYPE_MAP;
    }
}
