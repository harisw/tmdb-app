<?php

namespace App\Data\DTOs;

use App\Domain\Enums\ReasonType;
use Spatie\LaravelData\Data;

class SimilarityReasonData extends Data
{
    /**
     * @param  array<int, string>  $values
     */
    public function __construct(
        public readonly ReasonType $type,
        public readonly array $values,
        public readonly float $weight,
    ) {}
}
