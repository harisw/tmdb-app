<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\ProductionCompany;
use Illuminate\Database\Seeder;

class CountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "Adding Genre count \r\n";
        foreach (Genre::with('movies')->get() as $genre) {
            $genre->count = $genre->movies->count();
            $genre->save();
        }

        echo "Adding Language count \r\n";
        foreach (Language::with('movies')->get() as $lang) {
            $lang->count = $lang->movies->count();
            $lang->save();
        }
        echo "Adding Keyword count \r\n";
        foreach (Keyword::with('movies')->get() as $kw) {
            $kw->count = $kw->movies->count();
            $kw->save();
        }
        echo "Adding ProductionCompany count \r\n";
        foreach (ProductionCompany::with('movies')->get() as $pc) {
            $pc->count = $pc->movies->count();
            $pc->save();
        }
    }
}
