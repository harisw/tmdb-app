<?php

namespace App\Domain\Casts;

use App\Domain\Enums\Gender;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Hydrates TMDB's integer `gender` field into the Gender enum. Also accepts an
 * already-canonical string so a re-hydrated record round-trips.
 */
class TmdbGenderCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if ($value instanceof Gender) {
            return $value;
        }

        if (is_string($value) && $enum = Gender::tryFrom($value)) {
            return $enum;
        }

        return Gender::fromTmdb(is_numeric($value) ? (int) $value : null);
    }
}
