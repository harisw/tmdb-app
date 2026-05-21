<?php

namespace App\Data\DTOs;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Str;

class ProviderData extends Data
{
    #[Computed]
    public readonly string $slug;

    public function __construct(
        #[MapInputName('provider_id')]
        public readonly int $tmdb_id,
        #[MapInputName('provider_name')]
        public readonly string $name,
        #[MapInputName('logo_path')]
        public readonly string $img_logo,
        #[MapInputName('display_priority')]
        public readonly int $priority,
    ) {
        $this->slug = Str::slug($this->name);
    }

    public function toUpsertArray(): array
    {
        return array_filter($this->toArray(), fn ($v) => ! is_null($v));
    }
}
