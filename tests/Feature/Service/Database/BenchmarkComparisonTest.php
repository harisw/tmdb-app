<?php

namespace Tests\Feature\Service\Database;

use App\Services\Database\BenchmarkComparison;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BenchmarkComparisonTest extends TestCase
{
    /**
     * @return array<string, int|float>
     */
    private function metrics(array $overrides = []): array
    {
        return [
            'queries' => 3,
            'rows_written' => 450,
            'hot_updates' => 20,
            'wal_bytes' => 4_500_000,
            'wal_bytes_per_row' => 10_000.0,
            'duration_ms' => 800.0,
            'db_time_ms' => 700.0,
            'db_time_ms_per_row' => 1.556,
            ...$overrides,
        ];
    }

    private function compare(array $baseline, array $results): BenchmarkComparison
    {
        return new BenchmarkComparison(['popularity_sync' => $baseline], ['popularity_sync' => $results]);
    }

    public function test_has_no_warnings_when_results_match_the_baseline(): void
    {
        $comparison = $this->compare($this->metrics(), $this->metrics());

        $this->assertSame([], $comparison->warnings());
    }

    /**
     * @return array<string, array{array<string, int|float>, string}>
     */
    public static function regressions(): array
    {
        return [
            'more queries' => [['queries' => 4], 'queries'],
            'fewer HOT updates' => [['hot_updates' => 19], 'hot_updates'],
            'WAL per row over tolerance' => [['wal_bytes_per_row' => 11_600.0], 'wal_bytes_per_row'],
        ];
    }

    #[DataProvider('regressions')]
    public function test_warns_about_a_regression(array $overrides, string $metric): void
    {
        $warnings = $this->compare($this->metrics(), $this->metrics($overrides))->warnings();

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString("popularity_sync: {$metric}", $warnings[0]);
    }

    public function test_tolerates_wal_growth_within_fifteen_percent(): void
    {
        $comparison = $this->compare($this->metrics(), $this->metrics(['wal_bytes_per_row' => 11_400.0]));

        $this->assertSame([], $comparison->warnings());
    }

    public function test_never_warns_about_timings(): void
    {
        $comparison = $this->compare($this->metrics(), $this->metrics(['duration_ms' => 5_000.0, 'db_time_ms' => 4_000.0, 'db_time_ms_per_row' => 9.0]));

        $this->assertSame([], $comparison->warnings());
    }

    public function test_notes_a_changed_workload_without_warning(): void
    {
        $comparison = $this->compare($this->metrics(), $this->metrics(['rows_written' => 500]));

        $this->assertSame([], $comparison->warnings());
        $this->assertStringContainsString('workload changed', $comparison->toMarkdown());
    }

    public function test_reports_scenarios_without_a_baseline_as_new(): void
    {
        $comparison = new BenchmarkComparison(null, ['popularity_sync' => $this->metrics()]);

        $this->assertSame([], $comparison->warnings());
        $this->assertStringContainsString('No baseline', $comparison->toMarkdown());
    }

    public function test_renders_a_comment_with_a_marker_the_numbers_and_the_warnings(): void
    {
        $markdown = $this->compare($this->metrics(), $this->metrics(['queries' => 5]))->toMarkdown();

        $this->assertStringStartsWith(BenchmarkComparison::MARKER, $markdown);
        $this->assertStringContainsString('| queries | 3 | 5 | +2 |', $markdown);
        $this->assertStringContainsString('popularity_sync: queries', $markdown);
    }
}
