<?php

namespace App\Repositories\Contracts;

use App\Data\DTOs\TmdbApi\TmdbCreditData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface TitlePersonRepository
{
    /**
     * @param  Collection<int, TmdbCreditData>  $credits
     * @param  Collection<int, int>  $personIdsByTmdbId  tmdbId => local people.id
     * @return int rows written
     */
    public function upsertMany(Model $title, Collection $credits, Collection $personIdsByTmdbId): int;
}
