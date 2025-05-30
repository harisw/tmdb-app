<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\Movie;
use App\Models\ProductionCompany;
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
        $file = database_path('data/tmdb.csv');

        if (!file_exists($file) || !is_readable($file)) {
            throw new Exception("File not found");
        }

        $header = null;
        $data = [];

        if (($handle = fopen($file, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = $row;
                } else {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }


        foreach ($data as $row) {
            $genres = $this->prepareRelations($row, Genre::class);
            $companies = $this->prepareRelations($row, ProductionCompany::class);
            $keywords = $this->prepareRelations($row, Keyword::class);

            unset($row['genres'], $row['production_companies'], $row['keywords']);

            $row['slug'] = \Str::slug($row['title']);
            $movie = Movie::firstOrCreate($row);
            $movie->genres()->attach($genres->pluck('id')->toArray());
            $movie->companies()->attach($companies->pluck('id')->toArray());
            $movie->keywords()->attach($keywords->pluck('id')->toArray());
        }
    }

    private function prepareRelations(array &$row, $class): Collection
    {
        $result = collect();
        foreach (explode(',', $row['genres']) as $genre) {
            $cleanedGenre = trim($genre);
            $result->push(
                $class::firstOrCreate([
                    'slug' => \Str::slug($cleanedGenre),
                    'name' => trim($cleanedGenre),
                ])
            );
        }
        return $result;
    }

}
