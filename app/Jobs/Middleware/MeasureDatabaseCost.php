<?php

namespace App\Jobs\Middleware;

use App\Services\Database\QueryCounter;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @phpstan-type CostReport array{job_class: class-string, outcome: 'succeeded'|'failed', duration_ms: float, db_time_ms: float, queries: int, wal_bytes: int, rows_written: int, wal_bytes_per_row: float|null, db_time_ms_per_row: float|null, tables: array<string, array<string, int>>}
 */
class MeasureDatabaseCost
{
    private const COUNTERS = [
        'rows_inserted', 'rows_updated', 'hot_updates', 'rows_deleted',
        'heap_blocks_read', 'heap_blocks_hit', 'index_blocks_read', 'index_blocks_hit',
    ];

    /**
     * @param  list<string>  $tables
     */
    public function __construct(public readonly array $tables) {}

    /**
     * @param  Closure(object): mixed  $next
     */
    public function handle(object $job, Closure $next): mixed
    {
        $counter = app(QueryCounter::class);

        try {
            $before = $this->snapshot();
        } catch (Throwable $e) {
            report($e);

            return $next($job);
        }

        $queriesBefore = $counter->queries();
        $millisecondsBefore = $counter->milliseconds();
        $startedAt = hrtime(true);
        $outcome = 'failed';

        try {
            $result = $next($job);
            $outcome = 'succeeded';

            return $result;
        } finally {
            $durationMs = (hrtime(true) - $startedAt) / 1e6;
            $queries = $counter->queries() - $queriesBefore;
            $dbTimeMs = $counter->milliseconds() - $millisecondsBefore;

            try {
                DB::select('SELECT pg_stat_force_next_flush()');
                Log::info('Job database cost', $this->report($job, $outcome, $durationMs, $queries, $dbTimeMs, $before, $this->snapshot()));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @return array{wal_position: int, tables: array<string, array<string, int>>}
     */
    private function snapshot(): array
    {
        $placeholders = implode(',', array_fill(0, count($this->tables), '?'));

        $rows = DB::select(<<<SQL
            SELECT s.relname,
                   s.n_tup_ins + coalesce(x.n_tup_ins, 0) AS rows_inserted,
                   s.n_tup_upd + coalesce(x.n_tup_upd, 0) AS rows_updated,
                   s.n_tup_hot_upd + coalesce(x.n_tup_hot_upd, 0) AS hot_updates,
                   s.n_tup_del + coalesce(x.n_tup_del, 0) AS rows_deleted,
                   s.n_dead_tup AS dead_rows,
                   coalesce(io.heap_blks_read, 0) AS heap_blocks_read,
                   coalesce(io.heap_blks_hit, 0) AS heap_blocks_hit,
                   coalesce(io.idx_blks_read, 0) AS index_blocks_read,
                   coalesce(io.idx_blks_hit, 0) AS index_blocks_hit
            FROM pg_stat_user_tables s
            JOIN pg_statio_user_tables io ON io.relid = s.relid
            LEFT JOIN pg_stat_xact_user_tables x ON x.relid = s.relid
            WHERE s.schemaname = current_schema() AND s.relname IN ({$placeholders})
        SQL, $this->tables);

        return [
            'wal_position' => (int) DB::scalar("SELECT pg_wal_lsn_diff(pg_current_wal_insert_lsn(), '0/0')"),
            'tables' => collect($rows)
                ->keyBy('relname')
                ->map(fn (object $row) => array_map(intval(...), Arr::except((array) $row, 'relname')))
                ->all(),
        ];
    }

    /**
     * @param  array{wal_position: int, tables: array<string, array<string, int>>}  $before
     * @param  array{wal_position: int, tables: array<string, array<string, int>>}  $after
     * @return CostReport
     */
    private function report(object $job, string $outcome, float $durationMs, int $queries, float $dbTimeMs, array $before, array $after): array
    {
        $tables = collect($after['tables'])
            ->map(fn (array $counts, string $table) => [
                ...collect(self::COUNTERS)->mapWithKeys(fn (string $counter) => [$counter => $counts[$counter] - ($before['tables'][$table][$counter] ?? 0)]),
                'dead_rows' => $counts['dead_rows'],
            ])
            ->all();

        $rowsWritten = collect($tables)->sum(fn (array $counts) => $counts['rows_inserted'] + $counts['rows_updated'] + $counts['rows_deleted']);
        $walBytes = $after['wal_position'] - $before['wal_position'];

        return [
            'job_class' => $job::class,
            'outcome' => $outcome,
            'duration_ms' => round($durationMs, 1),
            'db_time_ms' => round($dbTimeMs, 1),
            'queries' => $queries,
            'wal_bytes' => $walBytes,
            'rows_written' => $rowsWritten,
            'wal_bytes_per_row' => $rowsWritten ? round($walBytes / $rowsWritten, 1) : null,
            'db_time_ms_per_row' => $rowsWritten ? round($dbTimeMs / $rowsWritten, 3) : null,
            'tables' => $tables,
        ];
    }
}
