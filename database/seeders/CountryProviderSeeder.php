<?php

namespace Database\Seeders;

use App\Services\CountryProviderService;
use Illuminate\Database\Seeder;

class CountryProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__.'/data/providers.json'), true);

        CountryProviderService::upsertBulk($data['results']);
    }
}
