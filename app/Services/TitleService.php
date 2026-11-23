<?php

namespace App\Services;

use App\Data\DTOs\TitleData;
use App\Data\DTOs\TmdbApi\TmdbGenreData;
use App\Data\DTOs\TmdbApi\TmdbKeywordData;
use App\Data\DTOs\TmdbApi\TmdbProductionCompanyData;
use App\Data\DTOs\TmdbApi\TmdbTitleData;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\ProductionCompany;
use App\Models\Title;
use DB;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;

class TitleService
{
    private int $insertedRowCount = 0;

    /** @var Collection<string, int> */
    private Collection $titleDict;

    /** @var array<class-string<Genre|ProductionCompany|Keyword>, Collection<string, int>> */
    private array $relationCache = [];

    public function __construct()
    {
        $this->loadCaches();
    }

    /**
     * @param  list<list<string|null>>  $data
     * @param  list<string|null>  $headers
     */
    public static function insertBulk(array $data, array $headers): int
    {
        return (new self)->insert($data, $headers);
    }

    /**
     * @param  list<list<string|null>>  $data
     * @param  list<string|null>  $headers
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
        if ($row->is_adult) {
            return null;
        }

        try {
            // NOTE: Transaction here is important for testing critical behavior to safely rollback for error
            return DB::transaction(function () use ($row) {
                $genres = $this->prepareRelationIds($row->genres, Genre::class);
                $companies = $this->prepareRelationIds($row->production_companies, ProductionCompany::class);
                $keywords = $row->keywords ? $this->prepareRelationIds($row->keywords, Keyword::class) : [];

                $title = Title::withoutGlobalScopes()->firstOrCreate(
                    ['slug' => $row->slug],
                    $row->except('genres', 'production_companies', 'keywords', 'belongs_to_collection', 'is_adult')->toArray()
                );
                $this->attachIgnoringDuplicates($title->genres(), $genres);
                $this->attachIgnoringDuplicates($title->companies(), $companies);
                $this->attachIgnoringDuplicates($title->keywords(), $keywords);

                $this->titleDict->put($title->slug, $title->id);
                $this->insertedRowCount++;

                return $title;
            });
        } catch (\Exception $e) {
            \Log::error('Title insert failed', [
                'tmdb_id' => $row->tmdb_id,
                'slug' => $row->slug,
                'exception' => $e,
            ]);
            $this->reloadRelationCache();
        }

        return null;
    }

    /**
     * Resolve the given related items to their ids, creating any that don't
     * exist yet. The cache holds slug => id (not full models) to keep memory
     * bounded across large imports. Returns a de-duplicated list of ids.
     *
     * @template TItem of TmdbGenreData|TmdbProductionCompanyData|TmdbKeywordData
     *
     * @param  DataCollection<int, TItem>  $items
     * @param  class-string<Genre|ProductionCompany|Keyword>  $class
     * @return list<int>
     */
    private function prepareRelationIds(DataCollection $items, string $class): array
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

    /**
     * @template TRelated of Genre|ProductionCompany|Keyword
     *
     * @param  BelongsToMany<TRelated, Title>  $relation
     * @param  list<int>  $ids
     */
    private function attachIgnoringDuplicates(BelongsToMany $relation, array $ids): void
    {
        $relation->newPivotStatement()->insertOrIgnore(array_map(fn (int $id): array => [
            $relation->getForeignPivotKeyName() => $relation->getParent()->getKey(),
            $relation->getRelatedPivotKeyName() => $id,
        ], $ids));
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
