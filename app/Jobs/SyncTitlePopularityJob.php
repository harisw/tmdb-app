<?php

namespace App\Jobs;

use App\Domain\Enums\TitleType;
use App\Jobs\Middleware\MeasureDatabaseCost;
use App\Models\Title;
use App\Services\TmdbApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SyncTitlePopularityJob implements ShouldQueue
{
    use Queueable;

    private const MIN_CHANGE = 0.5;

    public int $timeout = 900;

    public int $tries = 3;

    public int $backoff = 900;

    public function __construct(
        private readonly TitleType $titleType,
        private readonly int $batchSize = 1000,
        private readonly int $maxRows = 30_000,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new MeasureDatabaseCost(['titles'])];
    }

    public function handle(TmdbApiService $tmdb): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tmdb-export-') ?: throw new RuntimeException('Cannot create a temporary file for the TMDB export');

        try {
            $tmdb->downloadDailyExport($this->titleType, $path);
            $this->writeLargest($this->changesLargestFirst($path));
        } finally {
            File::delete($path);
        }
    }

    /**
     * @return array<int, float>
     */
    private function changesLargestFirst(string $path): array
    {
        $stored = Title::withoutGlobalScopes()
            ->where('type', $this->titleType->value)
            ->whereNotNull('tmdb_id')
            ->toBase()
            ->pluck('popularity', 'tmdb_id')
            ->all();

        $export = gzopen($path, 'rb') ?: throw new RuntimeException("Cannot open TMDB export {$path}");
        $popularities = [];
        $deltas = [];

        try {
            while (($line = gzgets($export)) !== false) {
                if (trim($line) === '') {
                    continue;
                }

                $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $tmdbId = $record['id'] ?? null;
                $popularity = $record['popularity'] ?? null;

                if (! isset($stored[$tmdbId]) || ! is_numeric($popularity)) {
                    continue;
                }

                $delta = abs($stored[$tmdbId] - $popularity);

                if ($delta > self::MIN_CHANGE) {
                    $popularities[$tmdbId] = (float) $popularity;
                    $deltas[$tmdbId] = $delta;
                }
            }
        } finally {
            gzclose($export);
        }

        return collect($deltas)
            ->sortDesc()
            ->map(fn (float $delta, int $tmdbId) => $popularities[$tmdbId])
            ->all();
    }

    /**
     * @param  array<int, float>  $changes
     */
    private function writeLargest(array $changes): void
    {
        $largest = array_slice($changes, 0, $this->maxRows, true);

        if (count($largest) < count($changes)) {
            Log::info('Title popularity sync capped', ['type' => $this->titleType->value, 'changed' => count($changes), 'written' => count($largest)]);
        }

        foreach (array_chunk($largest, $this->batchSize, true) as $batch) {
            $this->write($batch);
        }
    }

    /**
     * @param  array<int, float>  $popularityByTmdbId
     */
    private function write(array $popularityByTmdbId): void
    {
        $rows = implode(',', array_fill(0, count($popularityByTmdbId), '(CAST(? AS bigint), CAST(? AS double precision))'));
        $bindings = [];

        foreach ($popularityByTmdbId as $tmdbId => $popularity) {
            array_push($bindings, $tmdbId, $popularity);
        }

        DB::update(
            "UPDATE titles SET popularity = changes.popularity
            FROM (VALUES {$rows}) AS changes(tmdb_id, popularity)
            WHERE titles.tmdb_id = changes.tmdb_id AND titles.type = ?",
            [...$bindings, $this->titleType->value],
        );
    }
}
