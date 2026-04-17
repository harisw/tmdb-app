<?php

namespace App\DTOs;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Str;

class TitleDTO extends Data
{
    #[Computed]
    public readonly string $slug;

    public function __construct(
        public readonly string $name,
        #[MapInputName('id')]
        public readonly ?int $tmdb_id,
        public readonly float $rating,
        public readonly string $status,
        public readonly string $release_date,
        public readonly int $revenue,
        public readonly ?int $runtime,
        public readonly ?string $img_backdrop,
        public readonly ?string $imdb_id,
        public readonly ?string $overview,
        public readonly float $popularity,
        public readonly ?string $img_poster,
        public readonly ?string $tagline,
        public readonly int $language_id,
        public readonly ?string $homepage,
        // relation columns — kept separate, not part of title attributes
        public readonly string $genres,
        public readonly string $keywords,
        public readonly string $production_companies,
    ) {
        $this->slug = Str::slug($this->name);
    }
}
