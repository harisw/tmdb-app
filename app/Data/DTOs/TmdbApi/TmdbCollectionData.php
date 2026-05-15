<?php

namespace App\Data\DTOs\TmdbApi;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Str;

class TmdbCollectionData extends Data
{
    #[Computed]
    public readonly string $slug;

    public function __construct(
        #[MapInputName('id')]
        public readonly int $tmdb_id,
        public readonly string $name,
        public readonly ?string $poster_path,
        public readonly ?string $backdrop_path
    ) {
        $this->slug = Str::slug($this->name);
    }
}
