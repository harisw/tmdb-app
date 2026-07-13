<?php

namespace App\Repositories;

use App\Data\DTOs\TmdbApi\TmdbCreditData;
use App\Data\DTOs\TmdbApi\TmdbTitleData;
use App\Repositories\Contracts\TitlePersonRepository as TitlePersonRepositoryContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EloquentTitlePersonRepository implements TitlePersonRepositoryContract
{
    public function upsertMany(Model $title, Collection $credits, Collection $personIdsByTmdbId): int
    {
        if ($credits->isEmpty()) {
            return 0;
        }

        $now = now();

        $rows = $credits->map(fn (TmdbCreditData $c) => [
            'title_id' => $title->getKey(),
            'person_id' => $personIdsByTmdbId[$c->person->tmdbId],
            'job' => $c->job->value,
            'character' => $c->character,
            'order' => $c->order ?? 0,
            'episode_count' => $c->episodeCount,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        TmdbTitleData::upsert(
            $rows,
            uniqueBy: ['title_id', 'person_id', 'job'],
            update: ['character', 'order', 'episode_count', 'updated_at'],
        );

        return count($rows);
    }
}
