<?php

namespace App\Data\DTOs;

use DateTimeInterface;
use Spatie\LaravelData\Data;

class TitleSimilarityData extends Data
{
    public function __construct(
        public readonly int $titleId,
        public readonly int $similarTitleId,
        public readonly float $score,
        public readonly array $reasons,
    ) {}

    /**
     * Cosine similarity and the shared-signal reasons are symmetric, so a
     * single computed edge yields two rows: forward and reverse. Storing both
     * keeps graph lookups from either title cheap without re-materialising the
     * neighbour separately.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toUpsertRows(DateTimeInterface $now): array
    {
        return [
            $this->row($this->titleId, $this->similarTitleId, $now),
            $this->row($this->similarTitleId, $this->titleId, $now),
        ];
    }

    private function row(int $titleId, int $similarTitleId, DateTimeInterface $now): array
    {
        return [
            'title_id' => $titleId,
            'similar_title_id' => $similarTitleId,
            'score' => round($this->score, 6),
            'reasons' => json_encode($this->reasons),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
