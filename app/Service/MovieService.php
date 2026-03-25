<?php

namespace App\Service;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Movie;
use App\Models\ProductionCompany;
use Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MovieService
{
    private int $insertedRowCount = 0;

    private ?string $uploadId;

    public function __construct(?string $uploadId = null)
    {
        $this->uploadId = $uploadId;
    }

    public static function insertBulk($data, $headers, ?string $uploadId = null): int
    {
        $static = new self($uploadId);
        foreach ($data as $row) {
            $row = array_combine($headers, $row);

            $static->insertRow($row);
        }

        return $static->insertedRowCount;
    }

    public function insertRow(array $row): void
    {
        $row['slug'] = \Str::slug($row['title']);

        if (Movie::firstWhere('slug', $row['slug'])) {
            return;
        }

        try {
            $genres = $this->prepareRelations($row, Genre::class);
            $companies = $this->prepareRelations($row, ProductionCompany::class);
            $keywords = $this->prepareRelations($row, Keyword::class);

            unset($row['genres'], $row['production_companies'], $row['keywords']);

            $movie = Movie::create($row);
            $movie->genres()->attach($genres->pluck('id')->toArray());
            $movie->companies()->attach($companies->pluck('id')->toArray());
            $movie->keywords()->attach($keywords->pluck('id')->toArray());

            $this->insertedRowCount++;
            if ($this->uploadId) {
                $this->updateProgress();
            }
        } catch (\Exception $e) {
            if ($this->uploadId) {
                $this->updateProgress(true, $e->getMessage());
            }
        }
    }

    private function prepareRelations(array &$row, $class): Collection
    {
        $result = collect();
        $values = match ($class) {
            Genre::class => $row['genres'],
            ProductionCompany::class => $row['production_companies'],
            Keyword::class => $row['keywords']
        };
        foreach (explode(',', $values) as $val) {
            $cleanedVal = trim($val);
            $slug = Str::slug($cleanedVal);
            $result->push(
                $class::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'slug' => $slug,
                        'name' => trim($cleanedVal),
                    ])
            );
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
}
