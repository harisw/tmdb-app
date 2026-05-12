<?php

namespace App\DTOs;

use App\DTOs\TmdbApi\TmdbTitleData;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Str;

class TitleData extends Data
{
    #[Computed]
    public readonly string $slug;

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        #[MapInputName('id')]
        public readonly ?int $tmdb_id,
        public readonly float $rating,
        public readonly string $status,
        public readonly string $release_date,
        public readonly int $revenue,
        public readonly ?int $runtime,
        public readonly ?string $backdrop_path,
        public readonly ?string $imdb_id,
        public readonly ?string $overview,
        public readonly float $popularity,
        public readonly ?string $poster_path,
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

    public function toTmdbTitleData(): TmdbTitleData
    {
        return TmdbTitleData::from([
            'id' => $this->tmdb_id,
            'title' => $this->name,
            'type' => $this->type,
            'vote_average' => $this->rating,
            'status' => $this->status,
            'release_date' => $this->release_date,
            'revenue' => $this->revenue,
            'runtime' => $this->runtime,
            'backdrop_path' => $this->backdrop_path,
            'imdb_id' => $this->imdb_id,
            'overview' => $this->overview,
            'popularity' => $this->popularity,
            'poster_path' => $this->poster_path,
            'tagline' => $this->tagline,
            'language_id' => $this->language_id,
            'homepage' => $this->homepage,
            'genres' => $this->splitToNameArray($this->genres),
            'production_companies' => $this->splitToNameArray($this->production_companies),
            'belongs_to_collection' => null,
            'keywords' => $this->splitToNameArray($this->keywords),
        ]);
    }

    private function splitToNameArray(string $csv): array
    {
        return collect(explode(',', $csv))
            ->map(fn ($v) => ['name' => trim($v)])
            ->filter(fn ($v) => $v['name'] !== '')
            ->values()
            ->all();
    }
}
