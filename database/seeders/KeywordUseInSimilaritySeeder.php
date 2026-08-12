<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class KeywordUseInSimilaritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $blocked = require 'data/keyword_blocklist.php';
        DB::table('keywords')
            ->whereIn('slug', $blocked)
            ->update(['use_in_similarity' => false]);
    }
}
