<?php

namespace App\Data\DTOs\TmdbApi;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Str;

class TmdbPersonData extends Data
{
    #[Computed]
    public readonly string $slug;

    public function __construct(
        #[MapInputName('id')]
        public readonly int $tmdb_id,
        public readonly string $name,
        #[MapInputName('adult')]
        public readonly ?bool $is_adult,
        public readonly ?string $profile_path,
        public readonly ?string $imdb_id,
        public readonly ?string $overview,
        public readonly float $popularity,
        public readonly ?string $poster_path,
        public readonly ?string $tagline,
        public readonly int $language_id,
        public readonly ?string $homepage,
        #[DataCollectionOf(TmdbGenreData::class)]
        public readonly DataCollection $genres,
        DataCollection $production_companies,
        public readonly ?TmdbCollectionData $belongs_to_collection,
        #[DataCollectionOf(TmdbKeywordData::class)]
        public readonly ?DataCollection $keywords = null,
        public readonly ?array $extra_attributes = [],
    ) {
        $this->slug = Str::slug($this->name);
        $this->production_companies = new DataCollection(
            TmdbProductionCompanyData::class,
            $production_companies->toCollection()->unique('slug')->values()->all()
        );
    }
}
