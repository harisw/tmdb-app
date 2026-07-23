<?php

namespace Tests\Unit\Services\TmdbCredits;

use App\Data\DTOs\TmdbApi\TmdbCreditData;
use App\Domain\Enums\CastRanking;
use App\Domain\Enums\CreditJob;
use App\Services\TmdbCredits\TmdbCreditNormalizer;
use App\Services\TmdbCredits\TmdbCreditSelector;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The selector itself is pure and I/O-free, so this touches no database.
 * It still boots the app rather than extending PHPUnit's TestCase directly:
 * building the fixtures goes through TmdbCreditData::from(), and Spatie Data
 * resolves its casts out of the container.
 */
class TmdbCreditSelectorTest extends TestCase
{
    private TmdbCreditSelector $selector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new TmdbCreditSelector;
    }

    private function crew(int $id, string $name, float $popularity, string $job): array
    {
        return [
            'id' => $id, 'name' => $name, 'gender' => 2, 'adult' => false,
            'popularity' => $popularity, 'profile_path' => null,
            'credit_id' => "c{$id}", 'department' => 'Production', 'job' => $job,
        ];
    }

    /**
     * @param  array<int, array>  $crew
     * @return Collection<int, TmdbCreditData>
     */
    private function select(array $crew, int $castLimit = 10): Collection
    {
        $rows = collect(TmdbCreditNormalizer::fromCredits(['cast' => [], 'crew' => $crew]))
            ->map(fn (array $row) => TmdbCreditData::from($row));

        return $this->selector->select($rows, CastRanking::ORDER, $castLimit, 0);
    }

    private function namesFor(Collection $credits, CreditJob $job): array
    {
        return $credits
            ->filter(fn (TmdbCreditData $c) => $c->job === $job)
            ->map(fn (TmdbCreditData $c) => $c->person->name)
            ->values()
            ->all();
    }

    public function test_keeps_every_distinct_person_holding_the_same_job(): void
    {
        // Regression: these were grouped by job alone, folding all three into
        // whichever producer TMDB listed first. Saw X's real credits.
        $selected = $this->select([
            $this->crew(2147, 'Oren Koules', 0.3023, 'Producer'),
            $this->crew(2145, 'Mark Burg', 0.2828, 'Producer'),
            $this->crew(2127, 'James Wan', 2.2906, 'Executive Producer'),
        ]);

        $this->assertSame(
            ['Oren Koules', 'Mark Burg', 'James Wan'],
            $this->namesFor($selected, CreditJob::PRODUCER),
        );
    }

    public function test_ranks_a_senior_credit_above_a_more_popular_junior_one(): void
    {
        // The whole point of tiering: the film's actual producer is a nobody
        // and the executive producers are famous. The producer still wins.
        $selected = $this->select([
            $this->crew(2, 'Famous ExecP', 90.0, 'Executive Producer'),
            $this->crew(1, 'Lead Producer', 0.1, 'Producer'),
        ]);

        $this->assertSame(
            ['Lead Producer', 'Famous ExecP'],
            $this->namesFor($selected, CreditJob::PRODUCER),
        );
    }

    public function test_caps_producers_at_three_dropping_the_least_popular_junior(): void
    {
        $selected = $this->select([
            $this->crew(1, 'Lead Producer', 0.1, 'Producer'),
            $this->crew(2, 'Famous ExecP', 90.0, 'Executive Producer'),
            $this->crew(3, 'Another ExecP', 80.0, 'Executive Producer'),
            $this->crew(4, 'Third ExecP', 70.0, 'Executive Producer'),
        ]);

        $this->assertSame(
            ['Lead Producer', 'Famous ExecP', 'Another ExecP'],
            $this->namesFor($selected, CreditJob::PRODUCER),
        );
    }

    public function test_caps_directors_at_two_so_a_duo_survives_but_a_third_does_not(): void
    {
        $selected = $this->select([
            $this->crew(5, 'Anthony Russo', 8.0, 'Director'),
            $this->crew(6, 'Joe Russo', 7.0, 'Director'),
            $this->crew(7, 'Second Unit', 99.0, 'Co-Director'),
        ]);

        $this->assertSame(
            ['Anthony Russo', 'Joe Russo'],
            $this->namesFor($selected, CreditJob::DIRECTOR),
        );
    }

    public function test_collapses_one_person_holding_two_raw_jobs_into_a_single_row(): void
    {
        // The original purpose of collapseDuplicateJobs, preserved: title_person
        // is unique on (title_id, person_id, job), so this must not emit two.
        $selected = $this->select([
            $this->crew(9, 'Auteur', 5.0, 'Director'),
            $this->crew(9, 'Auteur', 5.0, 'Co-Director'),
        ]);

        $this->assertCount(1, $selected);
        $this->assertSame('Director, Co-Director', $selected->first()->rawJob);
    }

    public function test_every_selected_credit_is_unique_on_person_and_job(): void
    {
        // The invariant the ON CONFLICT upsert depends on: Postgres aborts the
        // whole batch if it names the same conflict target twice.
        $selected = $this->select([
            $this->crew(1, 'A', 1.0, 'Producer'),
            $this->crew(1, 'A', 1.0, 'Executive Producer'),
            $this->crew(1, 'A', 1.0, 'Director'),
            $this->crew(2, 'B', 2.0, 'Director'),
            $this->crew(2, 'B', 2.0, 'Co-Director'),
        ]);

        $keys = $selected->map(fn (TmdbCreditData $c) => $c->person->tmdbId.':'.$c->job->value);

        $this->assertSame($keys->count(), $keys->unique()->count());
    }
}
