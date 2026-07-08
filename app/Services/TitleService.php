<?php

namespace App\Services;

use App\Data\DTOs\TitleData;
use App\Data\DTOs\TmdbApi\TmdbTitleData;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\ProductionCompany;
use App\Models\Scopes\AdultContentScope;
use App\Models\Title;
use DB;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;

class TitleService
{
    private int $insertedRowCount = 0;

    private Collection $titleDict;

    private array $relationCache = [];

    public function __construct()
    {
        $this->loadCaches();
    }

    public static function insertBulk($data, $headers): int
    {
        return (new self)->insert($data, $headers);
    }

    /**
     * Insert a batch of rows, reusing the in-memory caches held by this
     * instance. Returns the number of rows inserted by this call only, so it
     * can be called repeatedly (e.g. once per batch) without reloading caches.
     */
    public function insert(array $data, array $headers): int
    {
        $before = $this->insertedRowCount;
        foreach ($data as $row) {
            $titleData = TitleData::from(array_combine($headers, $row));
            if ($this->titleDict->has($titleData->slug)) {
                continue;
            }

            $this->insertRow($titleData->toTmdbTitleData());
        }

        return $this->insertedRowCount - $before;
    }

    public function insertRow(TmdbTitleData $row): ?Title
    {
        try {
            // NOTE: Transaction here is important for testing critical behavior to safely rollback for error
            return DB::transaction(function () use ($row) {
                $genres = $this->prepareRelationIds($row->genres, Genre::class);
                $companies = $this->prepareRelationIds($row->production_companies, ProductionCompany::class);
                $keywords = $row->keywords ? $this->prepareRelationIds($row->keywords, Keyword::class) : [];

                // Import must see every title regardless of the viewer-facing
                // adult filter, otherwise re-importing an existing adult title
                // is hidden by AdultContentScope and hits the unique(slug) index.
                $title = Title::withoutGlobalScope(AdultContentScope::class)->firstOrCreate(
                    ['slug' => $row->slug],
                    $row->except('genres', 'production_companies', 'keywords', 'belongs_to_collection')->toArray()
                );
                $title->genres()->syncWithoutDetaching($genres);
                $title->companies()->syncWithoutDetaching($companies);
                $title->keywords()->syncWithoutDetaching($keywords);

                $this->titleDict->put($title->slug, $title->id);
                $this->insertedRowCount++;

                return $title;
            });
        } catch (\Exception $e) {
            \Log::error('Row: '.json_encode($row, JSON_PRETTY_PRINT));
            report($e);
            $this->reloadRelationCache();
        }

        return null;
    }

    /**
     * Resolve the given related items to their ids, creating any that don't
     * exist yet. The cache holds slug => id (not full models) to keep memory
     * bounded across large imports. Returns a de-duplicated list of ids.
     */
    private function prepareRelationIds(DataCollection $items, $class): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = $this->relationCache[$class]->get($item->slug);
            if (! $id) {
                $id = $class::firstOrCreate(['slug' => $item->slug], ['name' => $item->name])->id;
                $this->relationCache[$class]->put($item->slug, $id);
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    private function loadCaches(): void
    {
        $this->titleDict = DB::table('titles')->pluck('id', 'slug');
        $this->reloadRelationCache();
    }

    private function reloadRelationCache(): void
    {
        foreach ([Genre::class, ProductionCompany::class, Keyword::class] as $class) {
            $this->relationCache[$class] = $class::query()->pluck('id', 'slug');
        }
    }
}
