<?php

namespace App\Repositories\Contracts;

use App\Data\DTOs\TmdbApi\TmdbPersonData;
use Illuminate\Support\Collection;

interface PersonRepository
{
    /**
     * Upserts each person (deduped by tmdbId) and returns a map of
     * tmdbId => local people.id covering everything passed in.
     *
     * @param  Collection<int, TmdbPersonData>  $people
     * @return Collection<int, int>
     */
    public function upsertMany(Collection $people): Collection;
}
