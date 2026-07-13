<?php

namespace App\Services\TmdbCredits;

/**
 * Applies the importance rules that decide which normalized credits are
 * worth writing to the graph: cap + rank cast, collapse duplicate crew
 * job mappings. Pure and I/O-free by design — no Eloquent, no container —
 * so it's unit-testable against a plain array fixture without a database.
 */
class TmdbCreditSelector
{
    public function select(Collection $credits, string $rankCastBy, int $castLimit, int $minEpisodesForCast): Collection
    {
        $cast = $this->selectCast(
            $credits->filter(fn (CreditData $c) => $c->job === CreditJob::CAST),
            $rankCastBy,
            $castLimit,
            $minEpisodesForCast,
        );

        $crew = $this->collapseDuplicateJobs(
            $credits->reject(fn (CreditData $c) => $c->job === CreditJob::CAST)
        );

        return $cast->merge($crew)->values();
    }

    private function selectCast(Collection $cast, string $rankBy, int $limit, int $minEpisodes): Collection
    {
        $ranked = $rankBy === 'episode_count'
            ? $cast->filter(fn (CreditData $c) => ($c->episodeCount ?? 0) >= $minEpisodes)
                ->sortByDesc(fn (CreditData $c) => $c->episodeCount ?? 0)
            : $cast->sortBy(fn (CreditData $c) => $c->order ?? PHP_INT_MAX);

        return $ranked->take($limit);
    }

    /**
     * A person can pick up the same mapped CreditJob from two different raw
     * TMDB jobs (e.g. "Director" and "Co-Director" both -> DIRECTOR). Since
     * title_person is unique on (title_id, person_id, job), collapse those
     * into one row and sum episode counts rather than letting the second
     * write silently clobber the first.
     */
    private function collapseDuplicateJobs(Collection $crew): Collection
    {
        return $crew
            ->groupBy(fn (CreditData $c) => $c->person->tmdbId.':'.$c->job->value)
            ->map(function (Collection $group) {
                /** @var CreditData $first */
                $first = $group->first();

                return new CreditData(
                    person: $first->person,
                    job: $first->job,
                    rawJob: $group->pluck('rawJob')->unique()->implode(', '),
                    character: null,
                    episodeCount: $group->sum('episodeCount') ?: null,
                    order: 0,
                    creditId: $first->creditId,
                );
            })
            ->values();
    }
}
