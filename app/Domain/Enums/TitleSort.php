<?php

namespace App\Domain\Enums;

use App\Utils\Traits\EnumValues;

enum TitleSort: string
{
    use EnumValues;

    case POPULAR = 'popular';
    case NEWEST = 'newest';
    case RECENTLY_ADDED = 'added';

    public function label(): string
    {
        return match ($this) {
            self::POPULAR => 'Popular',
            self::NEWEST => 'Newest release',
            self::RECENTLY_ADDED => 'Recently added',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $sort) => ['value' => $sort->value, 'label' => $sort->label()],
            self::cases()
        );
    }
}
