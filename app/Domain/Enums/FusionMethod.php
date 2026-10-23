<?php

namespace App\Domain\Enums;

use Illuminate\Support\Collection;

enum FusionMethod: string
{
    case RECIPROCAL_RANK = 'rrf';
    case MIN_MAX = 'min-max';
    case Z_SCORE = 'z-score';

    private const RRF_K = 60;

    /**
     * @param  Collection<int, float>  $scores  title ID => score, best first, higher is better
     * @return Collection<int, float>
     */
    public function normalize(Collection $scores): Collection
    {
        if ($scores->isEmpty()) {
            return $scores;
        }

        return match ($this) {
            self::RECIPROCAL_RANK => $scores->keys()->mapWithKeys(fn (int $titleId, int $rank) => [$titleId => 1 / (self::RRF_K + $rank + 1)]),
            self::MIN_MAX => $this->minMax($scores),
            self::Z_SCORE => $this->zScores($scores),
        };
    }

    /**
     * @param  Collection<int, float>  $scores
     * @return Collection<int, float>
     */
    private function minMax(Collection $scores): Collection
    {
        $min = $scores->min();
        $range = $scores->max() - $min;

        return $scores->map(fn (float $score) => $range > 0 ? ($score - $min) / $range : 1.0);
    }

    /**
     * @param  Collection<int, float>  $scores
     * @return Collection<int, float>
     */
    private function zScores(Collection $scores): Collection
    {
        $mean = $scores->avg();
        $deviation = sqrt($scores->map(fn (float $score) => ($score - $mean) ** 2)->avg());

        if ($deviation == 0) {
            return $scores->map(fn () => 1.0);
        }

        $zScores = $scores->map(fn (float $score) => ($score - $mean) / $deviation);

        return $zScores->map(fn (float $zScore) => $zScore - $zScores->min());
    }
}
