<?php

namespace App\Data\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class ProviderData extends Data
{
    public function __construct(
        #[MapInputName('provider_id')]
        public readonly int $tmdb_id,
        #[MapInputName('provider_name')]
        public readonly string $name,
        #[MapInputName('logo_path')]
        public readonly ?string $img_logo,
        #[MapInputName('display_priority')]
        public readonly int $priority,
        /** @var array<string, int> */
        public readonly array $display_priorities = [],
    ) {}
}
