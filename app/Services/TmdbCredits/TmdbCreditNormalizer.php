<?php

namespace App\Services\TmdbCredits;

/**
 * Flattens TMDB's two credit response shapes into the array shape
 * App\Data\CreditData::from() expects (a nested `person` key plus flat
 * credit fields — see CreditData's docblock).
 *
 * - /movie/{id}/credits and /tv/{id}/season/{s}/episode/{e}/credits:
 *   cast[].character is a single string, crew[].job is a single string.
 *
 * - /tv/{id}/aggregate_credits:
 *   cast[].roles[] can hold multiple characters per person (recasts,
 *   multiple characters), and crew[].jobs[] can hold multiple jobs per
 *   person, each carrying its own episode_count. One TMDB crew entry can
 *   become several rows here — one per job.
 */
class TmdbCreditNormalizer
{
    public static function fromCredits(array $payload): array
    {
        $rows = collect();

        foreach ($payload['cast'] ?? [] as $person) {
            $rows->push(self::row(
                $person,
                job: 'cast',
                rawJob: 'Actor',
                character: $person['character'] ?? null,
                order: $person['order'] ?? null,
                episodeCount: null,
                creditId: $person['credit_id'] ?? null,
            ));
        }

        foreach ($payload['crew'] ?? [] as $person) {
            $rows->push(self::row(
                $person,
                job: $person['job'],
                rawJob: $person['job'],
                character: null,
                order: null,
                episodeCount: null,
                creditId: $person['credit_id'] ?? null,
            ));
        }

        return $rows->all();
    }

    public static function fromAggregateCredits(array $payload): array
    {
        $rows = collect();

        foreach ($payload['cast'] ?? [] as $person) {
            $roles = collect($person['roles'] ?? []);

            $rows->push(self::row(
                $person,
                job: 'cast',
                rawJob: 'Actor',
                character: $roles->pluck('character')->filter()->implode(' / ') ?: null,
                order: $person['order'] ?? null,
                episodeCount: $person['total_episode_count'] ?? null,
                creditId: $roles->first()['credit_id'] ?? null,
            ));
        }

        foreach ($payload['crew'] ?? [] as $person) {
            foreach ($person['jobs'] ?? [] as $jobEntry) {
                $rows->push(self::row(
                    $person,
                    job: $jobEntry['job'],
                    rawJob: $jobEntry['job'],
                    character: null,
                    order: null,
                    episodeCount: $jobEntry['episode_count'] ?? $person['total_episode_count'] ?? null,
                    creditId: $jobEntry['credit_id'] ?? null,
                ));
            }
        }

        return $rows->all();
    }

    /**
     * One TMDB `person` node (cast or crew) + one resolved job -> one row
     * shaped for CreditData::from(). The nested `person` array is
     * auto-hydrated into PersonData by Spatie Data.
     */
    private static function row(
        array $person,
        string $job,
        string $rawJob,
        ?string $character,
        ?int $order,
        ?int $episodeCount,
        ?string $creditId,
    ): array {
        return [
            'person' => [
                'tmdb_id' => $person['id'],
                'name' => $person['name'],
                'original_name' => $person['original_name'] ?? null,
                'gender' => $person['gender'] ?? 0,
                'is_adult' => $person['adult'] ?? false,
                'profile_path' => $person['profile_path'] ?? null,
                'popularity' => $person['popularity'] ?? 0.0,
                'known_for_department' => $person['known_for_department'] ?? null,
            ],
            'job' => $job,
            'raw_job' => $rawJob,
            'character' => $character,
            'episode_count' => $episodeCount,
            'order' => $order,
            'credit_id' => $creditId,
        ];
    }
}
