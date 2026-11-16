<?php

namespace App\Utils\Traits;

use Illuminate\Support\Collection;

trait EnumValues
{
    /**
     * @return list<int|string>
     */
    public static function values(): array
    {
        return array_map(
            static fn ($case) => $case->value,
            self::cases()
        );
    }

    /**
     * @return Collection<int, self>
     */
    public static function collection(): Collection
    {
        /** @var Collection<int, self> $cases */
        $cases = collect(self::cases());

        return $cases;
    }
}
