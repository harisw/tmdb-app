<?php

namespace App\Repositories\Contracts;

use App\Data\DTOs\TmdbApi\TmdbCreditData;
use Illuminate\Support\Collection;

interface TitlePersonRepository
{
    /**
     * Credits must already be unique on (person, job): the write targets a
     * unique index and Postgres rejects a batch naming the same conflict row
     * twice. See TmdbCreditSelector.
     *
     * @param  Collection<int, TmdbCreditData>  $credits
     * @param  Collection<int, int>  $personIdsByTmdbId  tmdbId => local people.id
     * @return int rows written
     */
    public function upsertMany(int $titleId, Collection $credits, Collection $personIdsByTmdbId): int;
}
