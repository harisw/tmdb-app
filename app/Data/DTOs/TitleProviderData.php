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
        public readonly int $country_provider_id,
        public readonly int $title_id,
        public readonly string $link,
        public readonly ?string $quality
    ) {}

    public function toUpsertArray(): array
    {
        return [
            'title_id' => $this->title_id,
            'country_provider_id' => $this->country_provider_id,
            'country_id' => $this->country_id,
            'provider_id' => $this->provider_id,
            'type' => $this->type->value,
            'link' => $this->link,
            'extra_attributes' => json_encode(['quality' => $this->quality]),
        ];
    }
}
