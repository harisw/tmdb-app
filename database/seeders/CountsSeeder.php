<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Adding Genre count \r\n";
        DB::statement('UPDATE genres SET count = (SELECT COUNT(*) FROM genre_title WHERE genre_id = genres.id)');

        echo "Adding Language count \r\n";
        DB::statement('UPDATE languages SET count = (SELECT COUNT(*) FROM titles WHERE language_id = languages.id)');

        echo "Adding Keyword count \r\n";
        DB::statement('UPDATE keywords SET count = (SELECT COUNT(*) FROM keyword_title WHERE keyword_id = keywords.id)');

        echo "Adding ProductionCompany count \r\n";
        DB::statement('UPDATE production_companies SET count = (SELECT COUNT(*) FROM title_production_company WHERE production_company_id = production_companies.id)');
    }
}
