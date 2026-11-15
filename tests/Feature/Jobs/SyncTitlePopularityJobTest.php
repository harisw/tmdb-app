<?php

namespace Tests\Feature\Jobs;

use App\Domain\Enums\TitleType;
use App\Jobs\Middleware\MeasureDatabaseCost;
use App\Jobs\SyncTitlePopularityJob;
use App\Models\Title;
use App\Services\TmdbApiService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Tests\TestCase;

class SyncTitlePopularityJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-13 08:30:00');
    }

    /**
     * @param  array<int, float>  $popularityByTmdbId
     */
    private function fakeExport(string $file, array $popularityByTmdbId): void
    {
        $lines = collect($popularityByTmdbId)
            ->map(fn (float $popularity, int $id) => json_encode(['id' => $id, 'original_name' => "Title {$id}", 'popularity' => $popularity]))
            ->implode("\n");

        Http::fake(["files.tmdb.org/p/exports/{$file}_11_13_2026.json.gz" => Http::response(gzencode($lines."\n"))]);
    }

    private function runJob(TitleType $type = TitleType::MOVIE, int $batchSize = 1000, int $maxRows = 30_000): void
    {
        (new SyncTitlePopularityJob($type, $batchSize, $maxRows))->handle(app(TmdbApiService::class));
    }

    public function test_updates_popularity_from_todays_movie_export(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 10, 'popularity' => 5]);
        $this->fakeExport('movie_ids', [10 => 42.5]);

        $this->runJob();

        $this->assertSame(42.5, $title->fresh()->popularity);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://files.tmdb.org/p/exports/movie_ids_11_13_2026.json.gz');
    }

    public function test_reads_the_tv_export_and_leaves_movies_with_the_same_tmdb_id_alone(): void
    {
        $show = Title::factory()->create(['tmdb_id' => 10, 'type' => TitleType::TV->value, 'popularity' => 5]);
        $movie = Title::factory()->create(['tmdb_id' => 10, 'popularity' => 5]);
        $this->fakeExport('tv_series_ids', [10 => 42.5]);

        $this->runJob(TitleType::TV);

        $this->assertSame(42.5, $show->fresh()->popularity);
        $this->assertSame(5.0, $movie->fresh()->popularity);
    }

    public function test_skips_changes_within_the_minimum_change(): void
    {
        $steady = Title::factory()->create(['tmdb_id' => 1, 'popularity' => 10]);
        $moved = Title::factory()->create(['tmdb_id' => 2, 'popularity' => 10]);
        $this->fakeExport('movie_ids', [1 => 10.5, 2 => 9.4]);

        $this->runJob();

        $this->assertSame(10.0, $steady->fresh()->popularity);
        $this->assertSame(9.4, $moved->fresh()->popularity);
    }

    public function test_ignores_unknown_ids_and_keeps_titles_missing_from_the_export(): void
    {
        $missing = Title::factory()->create(['tmdb_id' => 1, 'popularity' => 80]);
        $this->fakeExport('movie_ids', [999 => 12.0]);

        $this->runJob();

        $this->assertSame(80.0, $missing->fresh()->popularity);
        $this->assertDatabaseCount('titles', 1);
    }

    public function test_writes_in_batches_including_the_last_partial_one(): void
    {
        $titles = collect([1, 2, 3])->map(fn (int $id) => Title::factory()->create(['tmdb_id' => $id, 'popularity' => 0]));
        $this->fakeExport('movie_ids', [1 => 11.0, 2 => 22.0, 3 => 33.0]);
        $updates = 0;
        DB::listen(function (QueryExecuted $query) use (&$updates) {
            $updates += str_starts_with($query->sql, 'UPDATE titles') ? 1 : 0;
        });

        $this->runJob(batchSize: 2);

        $this->assertSame(2, $updates);
        $this->assertSame([11.0, 22.0, 33.0], $titles->map(fn (Title $title) => $title->fresh()->popularity)->all());
    }

    public function test_writes_only_the_largest_changes_when_over_the_per_run_cap(): void
    {
        $titles = collect([1, 2, 3])->map(fn (int $id) => Title::factory()->create(['tmdb_id' => $id, 'popularity' => 10]));
        $this->fakeExport('movie_ids', [1 => 15.0, 2 => 60.0, 3 => 0.0]);
        Log::spy();

        $this->runJob(maxRows: 2);

        $this->assertSame([10.0, 60.0, 0.0], $titles->map(fn (Title $title) => $title->fresh()->popularity)->all());
        Log::shouldHaveReceived('info')->once()->with('Title popularity sync capped', ['type' => 'movie', 'changed' => 3, 'written' => 2]);
    }

    public function test_does_not_log_a_cap_when_under_it(): void
    {
        Title::factory()->create(['tmdb_id' => 1, 'popularity' => 10]);
        $this->fakeExport('movie_ids', [1 => 15.0]);
        Log::spy();

        $this->runJob(maxRows: 1);

        Log::shouldNotHaveReceived('info');
    }

    public function test_keeps_the_stored_popularity_when_the_export_has_none(): void
    {
        $blank = Title::factory()->create(['tmdb_id' => 1, 'popularity' => 10]);
        $moved = Title::factory()->create(['tmdb_id' => 2, 'popularity' => 10]);
        Http::fake(['files.tmdb.org/*' => Http::response(gzencode("{\"id\":1,\"popularity\":null}\n{\"id\":2,\"popularity\":20.0}\n"))]);

        $this->runJob();

        $this->assertSame(10.0, $blank->fresh()->popularity);
        $this->assertSame(20.0, $moved->fresh()->popularity);
    }

    public function test_fails_on_a_truncated_export(): void
    {
        Title::factory()->create(['tmdb_id' => 10, 'popularity' => 5]);
        $lines = collect(range(1, 2000))->map(fn (int $id) => json_encode(['id' => $id, 'popularity' => $id / 7]))->implode("\n");
        $gzip = gzencode($lines);
        Http::fake(['files.tmdb.org/*' => Http::response(substr($gzip, 0, intdiv(strlen($gzip), 2)))]);

        $this->expectException(JsonException::class);

        $this->runJob();
    }

    public function test_leaves_updated_at_untouched(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 10, 'popularity' => 5, 'updated_at' => '2026-01-01 00:00:00']);
        $this->fakeExport('movie_ids', [10 => 42.5]);

        $this->runJob();

        $this->assertSame('2026-01-01 00:00:00', $title->fresh()->updated_at->toDateTimeString());
    }

    public function test_fails_without_writing_when_todays_export_is_missing(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 10, 'popularity' => 5]);
        Http::fake(['files.tmdb.org/*' => Http::response('Not Found', 404)]);

        $this->expectException(RequestException::class);

        try {
            $this->runJob();
        } finally {
            $this->assertSame(5.0, $title->fresh()->popularity);
        }
    }

    public function test_fails_on_a_malformed_export_line(): void
    {
        Title::factory()->create(['tmdb_id' => 10, 'popularity' => 5]);
        Http::fake(['files.tmdb.org/*' => Http::response(gzencode("{\"id\":10,\"popularity\":\n"))]);

        $this->expectException(JsonException::class);

        $this->runJob();
    }

    public function test_measures_its_database_cost_on_titles(): void
    {
        $middleware = (new SyncTitlePopularityJob(TitleType::MOVIE))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(MeasureDatabaseCost::class, $middleware[0]);
        $this->assertSame(['titles'], $middleware[0]->tables);
    }
}
