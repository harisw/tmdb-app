<?php

namespace App\Repositories;

use App\Data\DTOs\TmdbApi\TmdbPersonData;
use App\Models\Person;
use App\Repositories\Contracts\PersonRepository as PersonRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EloquentPersonRepository implements PersonRepositoryContract
{
    private const GENDER_MAP = [
        0 => 'not_specified',
        1 => 'female',
        2 => 'male',
        3 => 'non_binary',
    ];

    public function upsertMany(Collection $people): Collection
    {
        $unique = $people->unique(fn (TmdbPersonData $p) => $p->tmdbId)->values();

        if ($unique->isEmpty()) {
            return collect();
        }

        $now = now();

        $rows = $unique
            ->map(fn (TmdbPersonData $p) => [
                'name' => $p->name,
                'slug' => Str::slug($p->name).'-'.$p->tmdbId,
                'gender' => self::GENDER_MAP[$p->gender] ?? 'not_specified',
                'is_adult' => $p->isAdult,
                'profile_path' => $p->profilePath,
                'popularity' => $p->popularity,
                'tmdb_id' => $p->tmdbId,
                'extra_attributes' => json_encode([
                    'known_for_department' => $p->knownForDepartment,
                    'original_name' => $p->originalName,
                ]),
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
