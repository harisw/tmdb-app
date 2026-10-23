<?php

namespace Tests\Unit\Enums;

use App\Domain\Enums\FusionMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FusionMethodTest extends TestCase
{
    public function test_reciprocal_rank_scores_by_position_only(): void
    {
        $normalized = FusionMethod::RECIPROCAL_RANK->normalize(collect([7 => 0.9, 3 => 0.2, 5 => 0.1]));

        $this->assertSame([7 => 1 / 61, 3 => 1 / 62, 5 => 1 / 63], $normalized->all());
    }

    public function test_min_max_rescales_scores_between_zero_and_one(): void
    {
        $normalized = FusionMethod::MIN_MAX->normalize(collect([7 => 5.0, 3 => 3.0, 5 => 1.0]));

        $this->assertSame([7 => 1.0, 3 => 0.5, 5 => 0.0], $normalized->all());
    }

    public function test_z_score_measures_standard_deviations_above_the_lowest_score(): void
    {
        $normalized = FusionMethod::Z_SCORE->normalize(collect([7 => 5.0, 3 => 3.0, 5 => 1.0]));

        $this->assertEqualsWithDelta([7 => 2.4495, 3 => 1.2247, 5 => 0.0], $normalized->all(), 0.0001);
    }

    public function test_z_score_rewards_a_top_score_that_stands_out_more_than_min_max_does(): void
    {
        $standout = collect([1 => 0.9])->union(array_fill_keys(range(2, 20), 0.3));
        $even = collect(range(1, 20))->mapWithKeys(fn (int $titleId) => [$titleId => 0.9 - ($titleId - 1) * 0.6 / 19]);

        $this->assertSame(FusionMethod::MIN_MAX->normalize($standout)[1], FusionMethod::MIN_MAX->normalize($even)[1]);
        $this->assertGreaterThan(FusionMethod::Z_SCORE->normalize($even)[1], FusionMethod::Z_SCORE->normalize($standout)[1]);
    }

    /**
     * @return array<string, array{FusionMethod}>
     */
    public static function scoreBasedMethods(): array
    {
        return [
            'min-max' => [FusionMethod::MIN_MAX],
            'z-score' => [FusionMethod::Z_SCORE],
        ];
    }

    #[DataProvider('scoreBasedMethods')]
    public function test_gives_full_credit_when_every_score_is_equal(FusionMethod $method): void
    {
        $this->assertSame([4 => 1.0], $method->normalize(collect([4 => 0.37]))->all());
        $this->assertSame([4 => 1.0, 6 => 1.0], $method->normalize(collect([4 => 2.0, 6 => 2.0]))->all());
    }

    public function test_leaves_an_empty_list_empty(): void
    {
        foreach (FusionMethod::cases() as $method) {
            $this->assertTrue($method->normalize(collect())->isEmpty());
        }
    }
}
