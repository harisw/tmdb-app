<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['slug' => 'action', 'name' => 'Action'],
            ['slug' => 'science-fiction', 'name' => 'Science Fiction'],
            ['slug' => 'adventure', 'name' => 'Adventure'],
            ['slug' => 'drama', 'name' => 'Drama'],
            ['slug' => 'crime', 'name' => 'Crime'],
            ['slug' => 'thriller', 'name' => 'Thriller'],
            ['slug' => 'fantasy', 'name' => 'Fantasy'],
            ['slug' => 'comedy', 'name' => 'Comedy'],
            ['slug' => 'romance', 'name' => 'Romance'],
            ['slug' => 'western', 'name' => 'Western'],
            ['slug' => 'mystery', 'name' => 'Mystery'],
            ['slug' => 'war', 'name' => 'War'],
            ['slug' => 'animation', 'name' => 'Animation'],
            ['slug' => 'family', 'name' => 'Family'],
            ['slug' => 'horror', 'name' => 'Horror'],
            ['slug' => 'music', 'name' => 'Music'],
            ['slug' => 'history', 'name' => 'History'],
            ['slug' => 'tv-title', 'name' => 'TV Title'],
            ['slug' => 'documentary', 'name' => 'Documentary'],
            ['slug' => 'other', 'name' => 'Other'],

        ];

        foreach (array_chunk($data, 1000) as $chunkedData) {
            Genre::upsert($chunkedData, ['slug']);
        }
    }
}
