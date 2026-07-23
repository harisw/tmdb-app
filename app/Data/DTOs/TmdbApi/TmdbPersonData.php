<?php

namespace App\Data\DTOs\TmdbApi;

use App\Domain\Casts\TmdbGenderCast;
use App\Domain\Enums\Gender;
use Illuminate\Support\Str;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A TMDB person node, in the shape TMDB actually sends it — `id`, `adult`, and
 * an integer `gender`. Both producers (the /person seed dump and the credits
 * normalizer) hand this class raw TMDB keys, so the input contract stays
 * TMDB-shaped and the translation into our vocabulary happens here instead of
 * being re-invented at each call site.
 *
 * original_name and known_for_department are absent from the seed dump and only
 * appear on credit payloads, hence the defaults.
 */
#[MapInputName(SnakeCaseMapper::class)]
class TmdbPersonData extends Data
{
    #[Computed]
    public readonly string $slug;

    public function __construct(
        #[MapInputName('id')]
        public readonly int $tmdbId,

        public readonly string $name,

        #[WithCast(TmdbGenderCast::class)]
        public readonly Gender $gender,

        #[MapInputName('adult')]
        public readonly ?bool $isAdult,

        public readonly float $popularity,

        public readonly ?string $profilePath,

        public readonly ?string $originalName = null,

        public readonly ?string $knownForDepartment = null,
    ) {
        $this->slug = Str::slug($this->name).'-'.$this->tmdbId;
    }

    /**
     * Map the DTO to a `people` table row for bulk upserts.
     */
    public function toUpsertArray(): array
    {
        return [
            'tmdb_id' => $this->tmdbId,
            'name' => $this->name,
            'slug' => $this->slug,
            'gender' => $this->gender->value,
            'is_adult' => $this->isAdult ?? false,
            'popularity' => $this->popularity,
            'profile_path' => $this->profilePath,
        ];
    }
}
