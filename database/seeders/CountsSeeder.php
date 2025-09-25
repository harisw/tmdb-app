<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\ProductionCompany;
use Exception;
use Illuminate\Database\Seeder;

class CountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Genre::with('movies')->get() as $genre) {
            $genre->count = $genre->movies->count();
            $genre->save();
        }

        foreach (Language::with('movies')->get() as $lang) {
            $lang->count = $lang->movies->count();
            $lang->save();
        }

        foreach (Keyword::with('movies')->get() as $kw) {
            $kw->count = $kw->movies->count();
            $kw->save();
        }

        foreach (ProductionCompany::with('movies')->get() as $pc) {
            $pc->count = $pc->movies->count();
            $pc->save();
        }
    }
}
