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
        $path = tempnam(sys_get_temp_dir(), 'tmdb-export-');

        try {
            $tmdb->downloadDailyExport($this->titleType, $path);
            $this->syncFrom($path);
        } finally {
            File::delete($path);
        }
    }

    private function syncFrom(string $path): void
    {
        $stored = Title::withoutGlobalScopes()
            ->where('type', $this->titleType->value)
            ->whereNotNull('tmdb_id')
            ->toBase()
            ->pluck('popularity', 'tmdb_id')
            ->all();

        $export = gzopen($path, 'rb') ?: throw new RuntimeException("Cannot open TMDB export {$path}");
        $changed = [];
        $deltas = [];

        try {
            while (($line = gzgets($export)) !== false) {
                if (trim($line) === '') {
                    continue;
                }

                ['id' => $tmdbId, 'popularity' => $popularity] = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $delta = isset($stored[$tmdbId]) ? abs($stored[$tmdbId] - $popularity) : 0;

                if ($delta > self::MIN_CHANGE) {
                    $changed[$tmdbId] = (float) $popularity;
                    $deltas[$tmdbId] = $delta;
                }
            }
        } finally {
            gzclose($export);
        }

        arsort($deltas);
        $largest = array_intersect_key($changed, array_slice($deltas, 0, $this->maxRows, true));

        if (count($largest) < count($changed)) {
            Log::info('Title popularity sync capped', ['type' => $this->titleType->value, 'changed' => count($changed), 'written' => count($largest)]);
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
