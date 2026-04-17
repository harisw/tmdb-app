<?php

namespace App\Service;

use App\DTOs\TitleDTO;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\ProductionCompany;
use App\Models\Title;
use Artisan;
use Cache;
use DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TitleService
{
    private int $insertedRowCount = 0;

    private ?string $uploadId;

    private Collection $titleDict;

    private array $relationCache = [];

    public function __construct(?string $uploadId = null)
    {
        $this->uploadId = $uploadId;
        $this->loadCaches();
    }

    public static function insertBulk($data, $headers, ?string $uploadId = null): int
    {
        $static = new self($uploadId);
        foreach ($data as $row) {
            $static->insertRow(TitleDTO::from(array_combine($headers, $row)));
        }
        Artisan::call('titles:refresh-searchable');

        return $static->insertedRowCount;
    }

    public function insertRow(TitleDTO $row): void
    {
        if ($this->titleDict->has($row->slug)) {
            return;
        }

        try {
            DB::transaction(function () use ($row) {
                $genres = $this->prepareRelations($row->genres, Genre::class);
                $companies = $this->prepareRelations($row->production_companies, ProductionCompany::class);
                $keywords = $this->prepareRelations($row->keywords, Keyword::class);

                $title = Title::create($row->except('genres', 'production_companies', 'keywords')->toArray());
                $title->genres()->attach($genres->pluck('id')->toArray());
                $title->companies()->attach($companies->pluck('id')->toArray());
                $title->keywords()->attach($keywords->pluck('id')->toArray());
                $this->titleDict->put($title->slug, $title->id);

                $this->insertedRowCount++;
            });

            if ($this->uploadId) {
                $this->updateProgress();
            }
        } catch (\Exception $e) {
            report($e);
            $this->reloadRelationCache();

            if ($this->uploadId) {
                $this->updateProgress(true, $e->getMessage());
            }
        }
    }

    private function prepareRelations(string $values, $class): Collection
    {
        $result = collect();
        foreach (explode(',', $values) as $val) {
            $cleanedVal = ucwords(trim($val));
            $slug = Str::slug($cleanedVal);
            $relation = $this->relationCache[$class]->get($slug)
                ?? $class::create(['slug' => $slug, 'name' => $cleanedVal]);

            $result->push($relation);
            $this->relationCache[$class]->put($slug, $relation);
        }

        return $result;
    }

    /**
     * Update progress in cache
     */
    private function updateProgress($failed = false, $error = null): void
    {
        $cacheKey = "csv_upload_{$this->uploadId}";
        $progress = Cache::get($cacheKey);

        if ($progress) {
            $progress['processed_rows']++;

            if ($failed) {
                $progress['status'] = 'failed';
                $progress['error'] = $error;
            } elseif ($progress['processed_rows'] >= $progress['total_rows']) {
                $progress['status'] = 'completed';
            }

            Cache::put($cacheKey, $progress, now()->addHours(1));
        }
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
