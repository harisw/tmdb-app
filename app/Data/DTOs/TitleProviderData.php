<?php

namespace App\Data\DTOs;

use App\Domain\Casts\ProviderStreamTypeCast;
use App\Domain\Enums\StreamType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class TitleProviderData extends Data
{
    public function __construct(
        #[WithCast(ProviderStreamTypeCast::class)]
        public readonly StreamType $type,
        public readonly int $country_id,
        public readonly int $provider_id,
        public readonly int $title_id,
        public readonly string $link,
        public readonly string $quality
    ) {}

    public function toUpsertArray(): array
    {
        return $this
            ->only('country_id', 'provider_id', 'title_id', 'link')
            ->additional([
                'extra_attributes' => [
                    'quality' => $this->quality,
                ],
            ])
            ->toArray();
    }
}
