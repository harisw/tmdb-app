<?php

namespace App\Repositories;

use App\Data\DTOs\TmdbApi\TmdbPersonData;
use App\Models\Person;
use App\Repositories\Contracts\PersonRepository as PersonRepositoryContract;
use Illuminate\Support\Collection;

class EloquentPersonRepository implements PersonRepositoryContract
{
    public function upsertMany(Collection $people): Collection
    {
        $unique = $people->unique(fn (TmdbPersonData $p) => $p->tmdbId)->values();

        if ($unique->isEmpty()) {
            return collect();
        }

        $now = now();

        $rows = $unique
            ->map(fn (TmdbPersonData $p) => [
                ...$p->toUpsertArray(),
                'extra_attributes' => json_encode(array_filter([
                    'known_for_department' => $p->knownForDepartment,
                    'original_name' => $p->originalName,
                ])),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        Person::upsert(
            $rows,
            uniqueBy: ['tmdb_id'],
            update: ['name', 'gender', 'is_adult', 'profile_path', 'popularity', 'extra_attributes', 'updated_at'],
        );

        return Person::query()
            ->whereIn('tmdb_id', $unique->pluck('tmdbId'))
            ->pluck('id', 'tmdb_id');
    }
}
