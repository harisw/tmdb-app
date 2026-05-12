<?php

namespace App\Services;

use App\DTOs\TitleData;
use App\DTOs\TmdbApi\TmdbTitleData;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\ProductionCompany;
use App\Models\Title;
use Artisan;
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
        $static = new self;
        foreach ($data as $row) {
            $titleData = TitleData::from(array_combine($headers, $row));
            if ($static->titleDict->has($titleData->slug)) {
                continue;
            }

            $static->insertRow($titleData->toTmdbTitleData());
        }
        Artisan::call('titles:refresh-searchable');

        return $static->insertedRowCount;
    }

    public function insertRow(TmdbTitleData $row): void
    {
        try {
            // NOTE: Transaction here is important for testing critical behavior to safely rollback for error
            DB::transaction(function () use ($row) {
                $genres = $this->prepareRelations($row->genres, Genre::class);
                $companies = $this->prepareRelations($row->production_companies, ProductionCompany::class);
                $keywords = $row->keywords ? $this->prepareRelations($row->keywords, Keyword::class) : collect();

                $title = Title::create($row->except('genres', 'production_companies', 'keywords', 'belongs_to_collection')->toArray());
                $title->genres()->attach($genres->pluck('id')->toArray());
                $title->companies()->attach($companies->pluck('id')->toArray());
                $title->keywords()->attach($keywords->pluck('id')->toArray());

                $this->titleDict->put($title->slug, $title->id);
                $this->insertedRowCount++;
            });
        } catch (\Exception $e) {
            report($e);
            $this->reloadRelationCache();
        }
    }

    private function prepareRelations(DataCollection $items, $class): Collection
    {
        $result = collect();
        foreach ($items as $item) {
            $relation = $this->relationCache[$class]->get($item->slug)
                ?? $class::create(['slug' => $item->slug, 'name' => $item->name]);

            $result->push($relation);
            $this->relationCache[$class]->put($item->slug, $relation);
        }

        return $result;
    }

    private function loadCaches(): void
    {
        $this->titleDict = DB::table('titles')->pluck('id', 'slug');
        $this->reloadRelationCache();
    }

    private function reloadRelationCache(): void
    {
        foreach ([Genre::class, ProductionCompany::class, Keyword::class] as $class) {
            $this->relationCache[$class] = $class::all()->keyBy('slug');
        }
    }
}
