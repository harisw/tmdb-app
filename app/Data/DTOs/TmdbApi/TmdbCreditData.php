<?php

namespace App\Data\DTOs\TmdbApi;

use App\Domain\Casts\TmdbCreditJobCast;
use App\Domain\Enums\CreditJob;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class TmdbCreditData extends Data
{
    public function __construct(
        public TmdbPersonData $person,

        #[WithCast(TmdbCreditJobCast::class)]
        public ?CreditJob $job,

        public ?string $rawJob,
        public ?string $character,
        public ?int $episodeCount,
        public ?int $order,
        public ?string $creditId,
    ) {}
}
