<?php

namespace App\Data\DTOs\TmdbApi;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;
use Str;

class TmdbKeywordData extends Data
{
    #[Computed]
    public readonly string $slug;

    public function __construct(
        public readonly int $id,
        public readonly string $name
    ) {
        $this->slug = Str::slug($this->name);
    }
}
