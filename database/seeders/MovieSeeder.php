<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\Movie;
use App\Models\ProductionCompany;
use Exception;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class MovieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = database_path('data/10k_tmdb.csv');

        if (!file_exists($file) || !is_readable($file)) {
            throw new Exception("File not found");
        }

        $header = null;
        $data = [];

        if (($handle = fopen($file, 'r')) !== false) {
            while (($row = fgetcsv(stream: $handle, separator: '|')) !== false) {
                if (!$header) {
                    $header = $row;
                } else {
                    if (count($header) !== count($row)) {
                        var_dump("Broken row: ".json_encode($row));
                        continue;
                    }
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }


        foreach ($data as $row) {
            $row['slug'] = \Str::slug($row['title']);

            if (Movie::firstWhere('slug', $row['slug'])) {
                continue;
            }

            $genres = $this->prepareRelations($row, Genre::class);
            $companies = $this->prepareRelations($row, ProductionCompany::class);
            $keywords = $this->prepareRelations($row, Keyword::class);

            unset($row['genres'], $row['production_companies'], $row['keywords']);

            $movie = Movie::create($row);
            $movie->genres()->attach($genres->pluck('id')->toArray());
            $movie->companies()->attach($companies->pluck('id')->toArray());
            $movie->keywords()->attach($keywords->pluck('id')->toArray());
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
            $slug = \Str::slug($cleanedVal);
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

}
