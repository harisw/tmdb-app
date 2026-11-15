<?php

namespace App\Console\Commands;

use App\Domain\Enums\TitleType;
use App\Jobs\GenerateSearchEmbeddingsChunkJob;
use App\Jobs\Middleware\MeasureDatabaseCost;
use App\Jobs\SyncTitlePopularityJob;
use App\Services\Database\BenchmarkComparison;
use App\Services\Search\SearchDocumentBuilder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * @phpstan-import-type CostReport from MeasureDatabaseCost
 * @phpstan-import-type Metrics from BenchmarkComparison
 */
#[Signature('db:benchmark
    {--baseline=database/benchmarks/baseline.json : Baseline to compare against}
    {--output= : Write the results as JSON to this path}
    {--markdown= : Write the PR comment to this path}
    {--write-baseline : Replace the baseline with these results}')]
#[Description('Replay fixed workloads through measured jobs on a *_benchmark database and compare their cost with the baseline')]
class DatabaseBenchmark extends Command
{
    private const TITLES = 10_000;

    private const SEARCH_EMBEDDINGS = 5_000;

    private const SEARCH_CHUNK = 100;

    private const SEED = 0.42;

    private const MEASURED_TABLES = ['titles', 'title_search_embeddings'];

    /**
     * @var list<CostReport>
     */
    private array $reports = [];

    public function handle(): int
    {
        $database = DB::connection()->getDatabaseName();

        if (! str_ends_with($database, '_benchmark')) {
            $this->error("Refusing to run on \"{$database}\". db:benchmark truncates tables, so point DB_DATABASE at a database whose name ends in _benchmark.");

            return self::FAILURE;
        }

        Http::preventStrayRequests();
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            if ($event->message === 'Job database cost') {
                $this->reports[] = $event->context;
            }
        });

        $this->components->task('Seeding fixture', fn () => $this->seedFixture());

        $results = [];
        foreach (['popularity_sync' => $this->popularitySync(...), 'search_embeddings_chunk' => $this->searchEmbeddingsChunk(...)] as $scenario => $run) {
            $this->components->task($scenario, function () use ($scenario, $run, &$results) {
                $this->settle();
                $this->reports = [];
                $run();
                $results[$scenario] = $this->summarize(end($this->reports) ?: throw new RuntimeException("{$scenario} logged no database cost"));
            });
        }

        $this->table(['Scenario', 'Queries', 'Rows written', 'HOT updates', 'WAL bytes/row', 'DB ms'], collect($results)
            ->map(fn (array $r, string $scenario) => [$scenario, $r['queries'], $r['rows_written'], $r['hot_updates'], $r['wal_bytes_per_row'], $r['db_time_ms']])
            ->values()
            ->all());

        $this->writeOutputs($results);

        return self::SUCCESS;
    }

    private function seedFixture(): void
    {
        $tables = implode(', ', self::MEASURED_TABLES);

        DB::statement("TRUNCATE {$tables}, languages RESTART IDENTITY CASCADE");
        $indexes = $this->dropSecondaryIndexes();

        try {
            $this->insertRows();
        } finally {
            foreach ($indexes as $definition) {
                DB::statement($definition);
            }
        }

        foreach (self::MEASURED_TABLES as $table) {
            DB::statement("ALTER TABLE {$table} SET (autovacuum_enabled = false)");
        }
    }

    private function insertRows(): void
    {
        DB::select('SELECT setseed(?)', [self::SEED]);
        DB::insert("INSERT INTO languages (code, name) VALUES ('en', 'English')");

        DB::insert(<<<'SQL'
            INSERT INTO titles (name, slug, type, rating, status, popularity, language_id, tmdb_id,
                                overview, tagline, release_date, embedding, created_at, updated_at)
            SELECT 'Benchmark title ' || g, 'benchmark-title-' || g,
                   CASE WHEN g % 10 = 0 THEN 'tv' ELSE 'movie' END,
                   round((random() * 9 + 1)::numeric, 1), 'Released',
                   round((random() * 100)::numeric, 4), 1, g,
                   repeat('A benchmark overview sentence. ', 18), 'A benchmark tagline', date '2000-01-01' + g,
                   (SELECT array_agg(random() - 0.5)::vector(1536) FROM generate_series(1, 1536) WHERE g > 0),
                   now(), now()
            FROM generate_series(1, CAST(? AS int)) AS g
        SQL, [self::TITLES]);

        DB::insert(<<<'SQL'
            INSERT INTO title_search_embeddings (title_id, document_version, embedding, created_at, updated_at)
            SELECT g, ?, (SELECT array_agg(random() - 0.5)::vector(512) FROM generate_series(1, 512) WHERE g > 0)::halfvec(512), now(), now()
            FROM generate_series(CAST(? AS int), CAST(? AS int)) AS g
        SQL, [SearchDocumentBuilder::VERSION, self::SEARCH_CHUNK + 1, self::SEARCH_CHUNK + self::SEARCH_EMBEDDINGS]);
    }

    /**
     * @return list<string>
     */
    private function dropSecondaryIndexes(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::MEASURED_TABLES), '?'));
        $indexes = DB::select(<<<SQL
            SELECT i.indexrelid::regclass::text AS name, pg_get_indexdef(i.indexrelid) AS definition
            FROM pg_index i
            WHERE i.indrelid::regclass::text IN ({$placeholders})
              AND NOT EXISTS (SELECT 1 FROM pg_constraint c WHERE c.conindid = i.indexrelid)
            ORDER BY 1
        SQL, self::MEASURED_TABLES);

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX {$index->name}");
        }

        return array_column($indexes, 'definition');
    }

    private function settle(): void
    {
        foreach (self::MEASURED_TABLES as $table) {
            DB::statement("VACUUM (ANALYZE) {$table}");
        }

        DB::statement('CHECKPOINT');
    }

    private function popularitySync(): void
    {
        $lines = DB::table('titles')
            ->where('type', TitleType::MOVIE->value)
            ->orderBy('tmdb_id')
            ->pluck('popularity', 'tmdb_id')
            ->map(fn (float $popularity, int $tmdbId) => ['id' => $tmdbId, 'popularity' => round($popularity + ($tmdbId % 20 === 1 ? 3.0 : 0.1), 4)])
            ->concat(collect(range(1, 1000))->map(fn (int $i) => ['id' => self::TITLES * 2 + $i, 'popularity' => 1.0]))
            ->map(fn (array $line) => json_encode($line))
            ->implode("\n");

        Http::fake(['files.tmdb.org/*' => Http::response(gzencode($lines."\n"))]);

        SyncTitlePopularityJob::dispatchSync(TitleType::MOVIE);
    }

    private function searchEmbeddingsChunk(): void
    {
        mt_srand(42);
        config(['services.openai.key' => 'benchmark']);
        Http::fake(['api.openai.com/*' => fn ($request) => Http::response([
            'data' => collect($request['input'])
                ->map(fn (string $input, int $i) => ['index' => $i, 'embedding' => array_map(fn () => mt_rand() / mt_getrandmax() - 0.5, range(1, $request['dimensions']))])
                ->all(),
        ])]);

        GenerateSearchEmbeddingsChunkJob::dispatchSync(range(1, self::SEARCH_CHUNK));
    }

    /**
     * @param  CostReport  $report
     * @return Metrics
     */
    private function summarize(array $report): array
    {
        return [
            'queries' => $report['queries'],
            'rows_written' => $report['rows_written'],
            'hot_updates' => collect($report['tables'])->sum('hot_updates'),
            'wal_bytes' => $report['wal_bytes'],
            'wal_bytes_per_row' => $report['wal_bytes_per_row'],
            'duration_ms' => $report['duration_ms'],
            'db_time_ms' => $report['db_time_ms'],
            'db_time_ms_per_row' => $report['db_time_ms_per_row'],
        ];
    }

    /**
     * @param  array<string, Metrics>  $results
     */
    private function writeOutputs(array $results): void
    {
        $document = ['postgres' => DB::scalar('SHOW server_version'), 'scenarios' => $results];
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n";
        $baselinePath = base_path($this->option('baseline'));
        $baseline = File::exists($baselinePath) ? json_decode(File::get($baselinePath), true, flags: JSON_THROW_ON_ERROR)['scenarios'] : null;
        $comparison = new BenchmarkComparison($baseline, $results);

        foreach ($comparison->warnings() as $warning) {
            $this->warn($warning);
        }

        if ($this->option('output')) {
            File::put($this->option('output'), $json);
        }

        if ($this->option('markdown')) {
            File::put($this->option('markdown'), $comparison->toMarkdown());
        }

        if ($this->option('write-baseline')) {
            File::ensureDirectoryExists(dirname($baselinePath));
            File::put($baselinePath, $json);
            $this->info("Baseline written to {$this->option('baseline')}");
        }
    }
}
