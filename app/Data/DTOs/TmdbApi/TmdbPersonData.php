<?php

namespace App\Data\DTOs\TmdbApi;

use App\Domain\Transformers\GenderTransformer;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Str;

class TmdbPersonData extends Data
{
    #[Computed]
    public readonly string $slug;

    public function __construct(
        #[MapInputName('id')]
        public readonly int $tmdb_id,
        public readonly string $name,
        #[WithTransformer(GenderTransformer::class)]
        public readonly string $gender,
        #[MapInputName('adult')]
        public readonly ?bool $is_adult,
        public readonly float $popularity,
        public readonly ?string $profile_path,
        public readonly ?array $extra_attributes = [],
    ) {
        $this->slug = Str::slug($this->name).'-'.$this->tmdb_id;
    }

    /**
     * Map the DTO to a `people` table row for bulk upserts.
     */
    public function toUpsertArray(): array
    {
        $data = $this->toArray();

        return [
            'tmdb_id' => $data['tmdb_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'gender' => $data['gender'],
            'is_adult' => $data['is_adult'] ?? false,
            'popularity' => $data['popularity'],
            'profile_path' => $data['profile_path'],
        ];
    }
}
