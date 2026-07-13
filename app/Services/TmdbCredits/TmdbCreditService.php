<?php

namespace App\Services\TmdbCredits;

use App\Data\DTOs\TmdbApi\TmdbCreditData;
use App\Domain\Enums\CastRanking;
use App\Domain\Enums\TitleType;
use App\Models\Title;
use App\Repositories\Contracts\PersonRepository;
use App\Repositories\Contracts\TitlePersonRepository;
use Illuminate\Support\Collection;

/**
 * Orchestrates a TMDB credits import: normalize -> select -> persist.
 *
 * Depends on repository contracts rather than Eloquent models directly
 * (Dependency Inversion) — this class can be unit tested with fakes, and
 * the persistence layer can change without touching it. Stateless by
 * design: castLimit/minEpisodesForCast are per-call arguments, not
 * constructor config, so the container can resolve one shared instance.
 */
readonly class TmdbCreditService
{
    public function __construct(
        private TmdbCreditSelector $selector,
        private PersonRepository $people,
        private TitlePersonRepository $titlePeople,
    ) {}

    /**
     * The entry point callers should use. TmdbApiService::getCredits() already
     * decides /credits for movies and /aggregate_credits for TV, but returns
     * untagged JSON — so the caller would otherwise have to re-derive which
     * shape it got back. Keep this match in step with that one.
     *
     * @return int rows written to title_person
     */
    public function import(Title $title, array $payload, int $castLimit = 10): int
    {
        return match ($title->type) {
            TitleType::MOVIE => $this->importFromCredits($title, $payload, $castLimit),
            TitleType::TV => $this->importFromAggregateCredits($title, $payload, $castLimit),
        };
    }

    public function importFromCredits(Title $title, array $payload, int $castLimit = 10): int
    {
        return $this->persist(
            $title->getKey(),
            TmdbCreditNormalizer::fromCredits($payload),
            rankCastBy: CastRanking::ORDER,
            castLimit: $castLimit,
        );
    }

    public function importFromAggregateCredits(
        Title $title,
        array $payload,
        int $castLimit = 10,
        int $minEpisodesForCast = 1,
    ): int {
        return $this->persist(
            $title->getKey(),
            TmdbCreditNormalizer::fromAggregateCredits($payload),
            rankCastBy: CastRanking::EPISODE_COUNT,
            castLimit: $castLimit,
            minEpisodesForCast: $minEpisodesForCast,
        );
    }

    private function persist(
        int $titleId,
        array $rows,
        CastRanking $rankCastBy,
        int $castLimit,
        int $minEpisodesForCast = 1,
    ): int {
        // A null job is a TMDB job we deliberately don't model — see
        // TmdbCreditJobCast. Drop those before anything else looks at them.
        $credits = TmdbCreditData::collect($rows, Collection::class)
            ->filter(fn (TmdbCreditData $c) => $c->job !== null);

        $final = $this->selector->select($credits, $rankCastBy, $castLimit, $minEpisodesForCast);

        if ($final->isEmpty()) {
            return 0;
        }

        $personIdsByTmdbId = $this->people->upsertMany(
            $final->map(fn (TmdbCreditData $c) => $c->person)
        );

        return $this->titlePeople->upsertMany($titleId, $final, $personIdsByTmdbId);
    }
}
