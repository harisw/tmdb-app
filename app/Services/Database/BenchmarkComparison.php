<?php

namespace App\Services\Database;

class BenchmarkComparison
{
    public const MARKER = '<!-- db-benchmark -->';

    public const WAL_TOLERANCE = 0.15;

    private const METRICS = [
        'queries', 'rows_written', 'hot_updates', 'wal_bytes',
        'wal_bytes_per_row', 'duration_ms', 'db_time_ms', 'db_time_ms_per_row',
    ];

    /**
     * @param  array<string, array<string, int|float|null>>|null  $baseline
     * @param  array<string, array<string, int|float|null>>  $results
     */
    public function __construct(
        private readonly ?array $baseline,
        private readonly array $results,
    ) {}

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];

        foreach ($this->results as $scenario => $now) {
            $was = $this->baseline[$scenario] ?? null;

            if ($was === null) {
                continue;
            }

            if ($now['queries'] > $was['queries']) {
                $warnings[] = "{$scenario}: queries rose from {$was['queries']} to {$now['queries']}";
            }

            if ($now['hot_updates'] < $was['hot_updates']) {
                $warnings[] = "{$scenario}: hot_updates fell from {$was['hot_updates']} to {$now['hot_updates']}";
            }

            if ($was['wal_bytes_per_row'] && $now['wal_bytes_per_row'] > $was['wal_bytes_per_row'] * (1 + self::WAL_TOLERANCE)) {
                $warnings[] = sprintf(
                    '%s: wal_bytes_per_row rose from %s to %s (%s), over the %d%% tolerance',
                    $scenario, $was['wal_bytes_per_row'], $now['wal_bytes_per_row'],
                    $this->change($was['wal_bytes_per_row'], $now['wal_bytes_per_row']), self::WAL_TOLERANCE * 100,
                );
            }
        }

        return $warnings;
    }

    public function toMarkdown(): string
    {
        $warnings = $this->warnings();
        $lines = [self::MARKER, '### Database benchmark', ''];

        if ($this->baseline === null) {
            $lines[] = 'No baseline yet. Commit these results as `database/benchmarks/baseline.json` to start comparing.';
        } elseif ($warnings === []) {
            $lines[] = 'No regressions against the baseline.';
        } else {
            $lines[] = '**Warning:** this change makes database work more expensive than the baseline.';
            $lines[] = '';
            array_push($lines, ...array_map(fn (string $warning) => "- {$warning}", $warnings));
        }

        foreach ($this->results as $scenario => $now) {
            $was = $this->baseline[$scenario] ?? null;
            array_push($lines, '', "#### {$scenario}", '');

            if ($this->baseline !== null && $was === null) {
                array_push($lines, '_No baseline for this scenario yet._', '');
            } elseif ($was !== null && $was['rows_written'] !== $now['rows_written']) {
                array_push($lines, '_The workload changed (`rows_written` differs), so compare the per-row figures, not the totals._', '');
            }

            array_push($lines, '| Metric | Baseline | This PR | Change |', '| --- | ---: | ---: | ---: |');

            foreach (self::METRICS as $metric) {
                $lines[] = sprintf(
                    '| %s | %s | %s | %s |',
                    $metric, $this->format($was[$metric] ?? null), $this->format($now[$metric] ?? null),
                    $this->change($was[$metric] ?? null, $now[$metric] ?? null),
                );
            }
        }

        array_push(
            $lines, '',
            sprintf('Warnings cover more queries, fewer HOT updates, or WAL per row more than %d%% above the baseline. Timings vary on shared runners and are shown for information only.', self::WAL_TOLERANCE * 100),
        );

        return implode("\n", $lines)."\n";
    }

    private function format(int|float|null $value): string
    {
        return match (true) {
            $value === null => '—',
            is_int($value) => number_format($value),
            default => (string) $value,
        };
    }

    private function change(int|float|null $was, int|float|null $now): string
    {
        if ($was === null || $now === null) {
            return '—';
        }

        if ($was == $now) {
            return '0';
        }

        if (is_int($was) && is_int($now)) {
            return ($now > $was ? '+' : '').number_format($now - $was);
        }

        return $was == 0 ? '—' : sprintf('%+.1f%%', ($now - $was) / $was * 100);
    }
}
