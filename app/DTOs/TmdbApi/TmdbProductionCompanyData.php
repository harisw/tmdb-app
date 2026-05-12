<?php

namespace App\DTOs\TmdbApi;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;
use Str;

class TmdbProductionCompanyData extends Data
{
    #[Computed]
    public readonly string $slug;

    public function __construct(
        public readonly string $name
    ) {
        $this->slug = Str::slug($this->name);
    }
}
