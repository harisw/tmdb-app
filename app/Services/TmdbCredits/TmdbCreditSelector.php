<?php

namespace App\Services\TmdbCredits;

use App\Data\DTOs\TmdbApi\TmdbCreditData;
use App\Domain\Casts\TmdbCreditJobCast;
use App\Domain\Enums\CastRanking;
use App\Domain\Enums\CreditJob;
use Illuminate\Support\Collection;

/**
 * Applies the importance rules that decide which normalized credits are
 * worth writing to the graph: cap + rank cast, cap + rank crew per job,
 * collapse duplicate credits.
 * Pure and I/O-free by design — no Eloquent, no container — so it's
 * unit-testable against a plain array fixture without a database.
 *
 * Every path out of here must be unique on (person, job): title_person is
 * unique on (title_id, person_id, job) and we persist in one ON CONFLICT
 * upsert, which Postgres aborts outright ("cannot affect row a second time")
 * if a batch names the same conflict target twice. Deduplication is a
 * correctness requirement here, not a tidiness one.
 *
 * @see TmdbCreditService
 */
class TmdbCreditSelector
{
    /**
     * Top-N per crew job, applied after tier/popularity ranking.
     *
     * Directors are capped tighter than the rest: a legitimate directing duo
     * (the Coens, the Russos) is two people, and a third name on a film is
     * almost always a second-unit or episode director TMDB has flattened into
     * the same job.
     */
    private const CREW_LIMITS = [
        CreditJob::DIRECTOR->value => 2,
    ];

    private const DEFAULT_CREW_LIMIT = 3;

    /**
     * @param  Collection<int, TmdbCreditData>  $credits
     * @return Collection<int, TmdbCreditData>
     */
    public function select(Collection $credits, CastRanking $rankCastBy, int $castLimit, int $minEpisodesForCast): Collection
    {
        $cast = $this->selectCast(
            $credits->filter(fn (TmdbCreditData $c) => $c->job === CreditJob::CAST),
            $rankCastBy,
            $castLimit,
            $minEpisodesForCast,
        );

        $crew = $this->selectCrew(
            $credits->reject(fn (TmdbCreditData $c) => $c->job === CreditJob::CAST)
        );

        // A person can land in cast[] and also in crew[] under a job that maps
        // onto CAST ("Voice", "Cameo"). Both would target the same conflict
        // row. Cast wins — it carries the character and the billing order.
        return $cast->merge($crew)
            ->unique(fn (TmdbCreditData $c) => $this->conflictKey($c))
            ->values();
    }

    /**
     * @param  Collection<int, TmdbCreditData>  $cast
     * @return Collection<int, TmdbCreditData>
     */
    private function selectCast(Collection $cast, CastRanking $rankBy, int $limit, int $minEpisodes): Collection
    {
        $ranked = $rankBy === CastRanking::EPISODE_COUNT
            ? $cast->filter(fn (TmdbCreditData $c) => ($c->episodeCount ?? 0) >= $minEpisodes)
                ->sortByDesc(fn (TmdbCreditData $c) => $c->episodeCount ?? 0)
            : $cast->sortBy(fn (TmdbCreditData $c) => $c->order ?? PHP_INT_MAX);

        return $this->collapseDuplicateCast($ranked)->take($limit);
    }

    /**
     * /credits bills an actor once per character, so one person can occupy
     * several cast[] entries. Collapse to a single row — keeping the
     * best-ranked entry and joining the characters, which is what
     * aggregate_credits already does with its nested roles[]. This runs before
     * the take($limit) so a double-billed actor doesn't eat two cast slots.
     *
     * @param  Collection<int, TmdbCreditData>  $cast
     * @return Collection<int, TmdbCreditData>
     */
    private function collapseDuplicateCast(Collection $cast): Collection
    {
        return $cast
            ->groupBy(fn (TmdbCreditData $c) => $c->person->tmdbId)
            ->map(function (Collection $group) {
                /** @var TmdbCreditData $best */
                $best = $group->first();

                return new TmdbCreditData(
                    person: $best->person,
                    job: CreditJob::CAST,
                    rawJob: $best->rawJob,
                    character: $group->pluck('character')->filter()->unique()->implode(' / ') ?: null,
                    episodeCount: $best->episodeCount,
                    order: $best->order,
                    creditId: $best->creditId,
                );
            })
            ->values();
    }

    /**
     * TMDB's crew[] carries no billing order — unlike cast[], which has
     * `order` — so a cap needs a ranking of its own. Rank by credit tier
     * first (a Producer outranks an Executive Producer, a Director outranks a
     * Co-Director), then by person popularity within a tier, then keep the
     * top N per job.
     *
     * The cap matters: a studio film can list a dozen executive producers and
     * TV lists an art director per season/unit. Writing every one of them
     * spends a person row and a graph edge on a credit nobody picks a title
     * for, and dilutes the edges that do carry signal.
     *
     * @param  Collection<int, TmdbCreditData>  $crew
     * @return Collection<int, TmdbCreditData>
     */
    private function selectCrew(Collection $crew): Collection
    {
        return $this->collapseDuplicateJobs($crew)
            ->sortBy([
                fn (TmdbCreditData $a, TmdbCreditData $b) => TmdbCreditJobCast::tier($a->rawJob) <=> TmdbCreditJobCast::tier($b->rawJob),
                fn (TmdbCreditData $a, TmdbCreditData $b) => $b->person->popularity <=> $a->person->popularity,
            ])
            ->groupBy(fn (TmdbCreditData $c) => $c->job->value)
            ->flatMap(fn (Collection $group, string $job) => $group->take(self::CREW_LIMITS[$job] ?? self::DEFAULT_CREW_LIMIT))
            ->values();
    }

    /**
     * A person can pick up the same mapped CreditJob from two different raw
     * TMDB jobs (e.g. "Director" and "Co-Director" both -> DIRECTOR). Collapse
     * those into one row and sum episode counts rather than letting the second
     * one collide with the first.
     *
     * Grouped on (person, job), not job alone: every distinct person holding a
     * job is a credit we want. Grouping on the job by itself would fold all
     * three of a film's producers into whichever one TMDB happened to list
     * first, and stamp them with a rawJob ("Producer, Executive Producer")
     * that no one person actually held.
     *
     * @param  Collection<int, TmdbCreditData>  $crew
     * @return Collection<int, TmdbCreditData>
     */
    private function collapseDuplicateJobs(Collection $crew): Collection
    {
        return $crew
            ->groupBy(fn (TmdbCreditData $c) => $this->conflictKey($c))
            ->map(function (Collection $group) {
                /** @var TmdbCreditData $first */
                $first = $group->first();

                return new TmdbCreditData(
                    person: $first->person,
                    job: $first->job,
                    rawJob: $group->pluck('rawJob')->filter()->unique()->implode(', '),
                    character: null,
                    episodeCount: $group->sum('episodeCount') ?: null,
                    order: 0,
                    creditId: $first->creditId,
                );
            })
            ->values();
    }

    /**
     * The title_person unique key, minus the title.
     */
    private function conflictKey(TmdbCreditData $credit): string
    {
        return $credit->person->tmdbId.':'.$credit->job->value;
    }
}
