<?php

namespace App\Services\TmdbCredits;

/**
 * Orchestrates a TMDB credits import: normalize -> select -> persist.
 *
 * Depends on repository contracts rather than Eloquent models directly
 * (Dependency Inversion) — this class can be unit tested with fakes, and
 * the persistence layer can change without touching it. Stateless by
 * design: castLimit/minEpisodesForCast are per-call arguments, not
 * constructor config, so the container can resolve one shared instance.
 */
class TmdbCreditService
{
    public function __construct(
        private readonly TmdbCreditSelector $selector,
        private readonly PersonRepository $people,
        private readonly TitlePersonRepository $titlePeople,
    ) {}

    public function importFromCredits(Model $title, array $payload, int $castLimit = 10): int
    {
        return $this->import(
            $title,
            TmdbCreditNormalizer::fromCredits($payload),
            rankCastBy: 'order',
            castLimit: $castLimit,
        );
    }

    public function importFromAggregateCredits(
        Model $title,
        array $payload,
        int $castLimit = 10,
        int $minEpisodesForCast = 1,
    ): int {
        return $this->import(
            $title,
            TmdbCreditNormalizer::fromAggregateCredits($payload),
            rankCastBy: 'episode_count',
            castLimit: $castLimit,
            minEpisodesForCast: $minEpisodesForCast,
        );
    }

    private function import(
        Model $title,
        array $rows,
        string $rankCastBy,
        int $castLimit,
        int $minEpisodesForCast = 1,
    ): int {
        $credits = CreditData::collect($rows, Collection::class)
            ->filter(fn (CreditData $c) => $c->job !== null);

        $final = $this->selector->select($credits, $rankCastBy, $castLimit, $minEpisodesForCast);

        if ($final->isEmpty()) {
            return 0;
        }

        $personIdsByTmdbId = $this->people->upsertMany(
            $final->map(fn (CreditData $c) => $c->person)
        );

        return $this->titlePeople->upsertMany($title, $final, $personIdsByTmdbId);
    }
}
