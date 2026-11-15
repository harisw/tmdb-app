<?php

namespace Tests\Feature\Jobs\Middleware;

use App\Domain\Enums\TitleType;
use App\Jobs\Middleware\MeasureDatabaseCost;
use App\Jobs\SyncTitlePopularityJob;
use App\Models\Title;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MeasureDatabaseCostTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $tables
     * @return array<string, mixed>
     */
    private function measure(array $tables, Closure $work): array
    {
        Log::spy();
        $job = new SyncTitlePopularityJob(TitleType::MOVIE);

        try {
            (new MeasureDatabaseCost($tables))->handle($job, fn () => $work());
        } finally {
            Log::shouldHaveReceived('info')->once()->with('Job database cost', Mockery::capture($context));
        }

        return $context;
    }

    /**
     * @param  Collection<int, Title>  $titles
     */
    private function updatePopularity(Collection $titles): void
    {
        DB::table('titles')->whereIn('id', $titles->modelKeys())->update(['popularity' => 99]);
    }

    public function test_logs_the_rows_queries_and_wal_the_job_produced(): void
    {
        $titles = Title::factory()->count(3)->create();
        $deleted = Title::factory()->create();

        $cost = $this->measure(['titles'], function () use ($titles, $deleted) {
            $this->updatePopularity($titles);
            DB::table('titles')->where('id', $deleted->id)->delete();
        });

        $this->assertSame(SyncTitlePopularityJob::class, $cost['job_class']);
        $this->assertSame('succeeded', $cost['outcome']);
        $this->assertSame(2, $cost['queries']);
        $this->assertSame(4, $cost['rows_written']);
        $this->assertGreaterThan(0, $cost['wal_bytes']);
        $this->assertSame(round($cost['wal_bytes'] / 4, 1), $cost['wal_bytes_per_row']);
        $this->assertGreaterThan(0, $cost['db_time_ms']);
        $this->assertGreaterThanOrEqual($cost['db_time_ms'], $cost['duration_ms']);
        $this->assertSame(['titles'], array_keys($cost['tables']));
        $this->assertSame(0, $cost['tables']['titles']['rows_inserted']);
        $this->assertSame(3, $cost['tables']['titles']['rows_updated']);
        $this->assertSame(1, $cost['tables']['titles']['rows_deleted']);
        $this->assertContains($cost['tables']['titles']['hot_updates'], [0, 1, 2, 3]);
        foreach (['dead_rows', 'heap_blocks_read', 'heap_blocks_hit', 'index_blocks_read', 'index_blocks_hit'] as $metric) {
            $this->assertIsInt($cost['tables']['titles'][$metric]);
        }
    }

    public function test_reports_only_the_requested_tables(): void
    {
        $titles = Title::factory()->count(2)->create();

        $cost = $this->measure(['languages'], fn () => $this->updatePopularity($titles));

        $this->assertSame(['languages'], array_keys($cost['tables']));
        $this->assertSame(0, $cost['tables']['languages']['rows_updated']);
        $this->assertSame(0, $cost['rows_written']);
    }

    public function test_leaves_per_row_figures_empty_when_nothing_was_written(): void
    {
        $cost = $this->measure(['titles'], fn () => DB::select('SELECT 1'));

        $this->assertSame(1, $cost['queries']);
        $this->assertNull($cost['wal_bytes_per_row']);
        $this->assertNull($cost['db_time_ms_per_row']);
    }

    public function test_logs_a_failed_job_and_rethrows_its_exception(): void
    {
        $titles = Title::factory()->count(1)->create();

        $this->expectExceptionObject(new RuntimeException('TMDB export missing'));

        try {
            $this->measure(['titles'], function () use ($titles) {
                $this->updatePopularity($titles);
                throw new RuntimeException('TMDB export missing');
            });
        } finally {
            Log::shouldHaveReceived('info')->with('Job database cost', Mockery::on(
                fn (array $cost) => $cost['outcome'] === 'failed' && $cost['tables']['titles']['rows_updated'] === 1,
            ));
        }
    }

    public function test_keeps_the_jobs_own_exception_when_measuring_after_it_fails(): void
    {
        Log::spy();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('missing_table');

        (new MeasureDatabaseCost(['titles']))->handle(
            new SyncTitlePopularityJob(TitleType::MOVIE),
            fn () => DB::select('SELECT * FROM missing_table'),
        );
    }

    public function test_runs_the_job_unmeasured_when_the_first_snapshot_fails(): void
    {
        Exceptions::fake();
        DB::shouldReceive('select')->andThrow(new RuntimeException('Statistics unavailable'));
        Log::spy();

        $result = (new MeasureDatabaseCost(['titles']))->handle(new SyncTitlePopularityJob(TitleType::MOVIE), fn () => 'done');

        $this->assertSame('done', $result);
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Statistics unavailable');
        Log::shouldNotHaveReceived('info');
    }
}
