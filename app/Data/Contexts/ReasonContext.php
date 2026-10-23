<?php

namespace App\Data\Contexts;

use App\Data\DTOs\SimilarityReasonData;
use App\Models\Title;
use Closure;
use Illuminate\Database\Eloquent\Collection;

class ReasonContext
{
    /** @var list<SimilarityReasonData> */
    private array $reasons = [];

    public function __construct(
        public readonly Title $titleA,
        public readonly Title $titleB,
        public readonly float $score,
    ) {}

    public function addReason(SimilarityReasonData $reason): void
    {
        $this->reasons[] = $reason;
    }

    /**
     * @param  Closure(Title): Collection  $related
     * @return array<int, string>
     */
    public function sharedNames(Closure $related): array
    {
        return $related($this->titleA)
            ->whereIn('id', $related($this->titleB)->modelKeys())
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return list<SimilarityReasonData>
     */
    public function getSortedReasons(): array
    {
        usort($this->reasons, fn (SimilarityReasonData $a, SimilarityReasonData $b) => $b->weight <=> $a->weight);

        return $this->reasons;
    }
}
