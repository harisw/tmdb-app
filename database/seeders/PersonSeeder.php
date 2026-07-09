<?php

namespace Database\Seeders;

use App\Data\DTOs\TmdbApi\TmdbPersonData;
use App\Models\Person;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class PersonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__.'/data/people.json'), true);

        $records = TmdbPersonData::collect(
            Collection::make($data)->unique('id')
        )
            ->toBase()
            ->map(fn (TmdbPersonData $person) => $person->toUpsertArray())
            ->all();

        foreach (array_chunk($records, 1000) as $chunk) {
            Person::upsert($chunk, ['tmdb_id']);
        }
    }
}
