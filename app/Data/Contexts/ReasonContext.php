<?php

namespace App\Data\Contexts;

use App\Models\Title;

class ReasonContext
{
    public array $reasons = [];

    public function __construct(
        public readonly Title $titleA,
        public readonly Title $titleB,
        public readonly float $score,
    ) {}

    public function addReason(array $reason): void
    {
        $this->reasons[] = $reason;
    }

    public function getSortedReasons(): array
    {
        usort($this->reasons, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        return $this->reasons;
    }
}
