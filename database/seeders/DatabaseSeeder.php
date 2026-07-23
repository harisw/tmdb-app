<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            GenreSeeder::class,
            ProductionCompanySeeder::class,
            KeywordSeeder::class,
            CountrySeeder::class,
            PersonSeeder::class,
            UserSeeder::class,
            //            CountryProviderSeeder::class,
            //            TitleSeeder::class,
            //            CountsSeeder::class,
        ]);
    }
}
