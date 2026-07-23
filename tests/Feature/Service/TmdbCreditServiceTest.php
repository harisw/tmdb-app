<?php

namespace Tests\Feature\Service;

use App\Domain\Enums\CreditJob;
use App\Domain\Enums\Gender;
use App\Domain\Enums\TitleType;
use App\Models\Person;
use App\Models\Title;
use App\Services\TmdbCredits\TmdbCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmdbCreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private TmdbCreditService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TmdbCreditService::class);
    }

    // -------------------------------------------------------------------------
    // Payload builders — mirror the shapes TMDB actually returns
    // -------------------------------------------------------------------------

    /** A TMDB person node, as it appears inside cast[] / crew[]. */
    private function personNode(int $id, string $name = 'Jane Doe', array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'name' => $name,
            'original_name' => $name,
            'adult' => false,
            'gender' => 1,
            'popularity' => 12.5,
            'profile_path' => '/jane.jpg',
            'known_for_department' => 'Acting',
        ], $overrides);
    }

    /** /movie/{id}/credits — cast[].character and crew[].job are single strings. */
    private function creditsPayload(array $cast = [], array $crew = []): array
    {
        return ['cast' => $cast, 'crew' => $crew];
    }

    private function castEntry(int $id, string $name, string $character, int $order): array
    {
        return $this->personNode($id, $name) + [
            'character' => $character,
            'order' => $order,
            'credit_id' => "credit-{$id}-{$order}",
        ];
    }

    private function crewEntry(int $id, string $name, string $job): array
    {
        return $this->personNode($id, $name) + [
            'job' => $job,
            'department' => 'Directing',
            'credit_id' => "credit-{$id}-".strtolower($job),
        ];
    }

    /** /tv/{id}/aggregate_credits — roles[] and jobs[] are nested, with episode counts. */
    private function aggregateCastEntry(int $id, string $name, array $roles, int $totalEpisodes, int $order = 0): array
    {
        return $this->personNode($id, $name) + [
            'roles' => $roles,
            'total_episode_count' => $totalEpisodes,
            'order' => $order,
        ];
    }

    private function aggregateCrewEntry(int $id, string $name, array $jobs): array
    {
        return $this->personNode($id, $name) + [
            'jobs' => $jobs,
            'department' => 'Directing',
        ];
    }

    private function movie(): Title
    {
        return Title::factory()->create(['type' => TitleType::MOVIE->value]);
    }

    private function show(): Title
    {
        return Title::factory()->create(['type' => TitleType::TV->value]);
    }

    // -------------------------------------------------------------------------
    // Movie credits — happy path
    // -------------------------------------------------------------------------

    public function test_imports_cast_and_crew_from_movie_credits(): void
    {
        $title = $this->movie();

        $written = $this->service->import($title, $this->creditsPayload(
            cast: [$this->castEntry(1, 'Lead Actor', 'Hero', 0)],
            crew: [$this->crewEntry(2, 'The Director', 'Director')],
        ));

        $this->assertSame(2, $written);

        $actor = Person::where('tmdb_id', 1)->sole();
        $this->assertDatabaseHas('title_person', [
            'title_id' => $title->id,
            'person_id' => $actor->id,
            'job' => CreditJob::CAST->value,
            'character' => 'Hero',
            'order' => 0,
        ]);

        $this->assertDatabaseHas('title_person', [
            'title_id' => $title->id,
            'person_id' => Person::where('tmdb_id', 2)->sole()->id,
            'job' => CreditJob::DIRECTOR->value,
        ]);
    }

    public function test_ranks_cast_by_billing_order_and_caps_at_the_limit(): void
    {
        $title = $this->movie();

        $this->service->import($title, $this->creditsPayload(cast: [
            $this->castEntry(3, 'Third Billed', 'C', 2),
            $this->castEntry(1, 'First Billed', 'A', 0),
            $this->castEntry(2, 'Second Billed', 'B', 1),
        ]), castLimit: 2);

        $imported = $title->cast()->with('person')->get();

        $this->assertSame(2, $imported->count());
        $this->assertSame(
            ['First Billed', 'Second Billed'],
            $imported->pluck('person.name')->all(),
        );
    }

    public function test_maps_tmdb_job_strings_onto_the_credit_job_taxonomy(): void
    {
        $title = $this->movie();

        $this->service->import($title, $this->creditsPayload(crew: [
            $this->crewEntry(1, 'A', 'Screenplay'),
            $this->crewEntry(2, 'B', 'Original Music Composer'),
            $this->crewEntry(3, 'C', 'Production Design'),
            $this->crewEntry(4, 'D', 'Executive Producer'),
        ]));

        $this->assertEqualsCanonicalizing(
            [
                CreditJob::WRITER,
                CreditJob::COMPOSER,
                CreditJob::ART_DIRECTOR,
                CreditJob::PRODUCER,
            ],
            $title->credits()->pluck('job')->all(),
        );
    }

    public function test_skips_crew_jobs_that_are_not_in_the_taxonomy(): void
    {
        $title = $this->movie();

        $written = $this->service->import($title, $this->creditsPayload(crew: [
            $this->crewEntry(1, 'Stunt Guy', 'Stunt Double'),
            $this->crewEntry(2, 'Bean Counter', 'Assistant Accountant'),
            $this->crewEntry(3, 'The Director', 'Director'),
        ]));

        $this->assertSame(1, $written);
        $this->assertDatabaseCount('title_person', 1);
        // Unmapped crew must not create people either — they're dropped before
        // the person upsert, not after.
        $this->assertDatabaseCount('people', 1);
    }

    // -------------------------------------------------------------------------
    // Deduplication — every one of these would abort the ON CONFLICT upsert
    // -------------------------------------------------------------------------

    public function test_collapses_an_actor_billed_twice_for_two_characters(): void
    {
        $title = $this->movie();

        $written = $this->service->import($title, $this->creditsPayload(cast: [
            $this->castEntry(1, 'Dual Role', 'Good Twin', 0),
            $this->castEntry(1, 'Dual Role', 'Evil Twin', 1),
        ]));

        $this->assertSame(1, $written);
        $this->assertDatabaseHas('title_person', [
            'title_id' => $title->id,
            'person_id' => Person::where('tmdb_id', 1)->sole()->id,
            'job' => CreditJob::CAST->value,
            'character' => 'Good Twin / Evil Twin',
            'order' => 0,
        ]);
    }

    public function test_a_duplicate_billing_does_not_consume_two_cast_slots(): void
    {
        $title = $this->movie();

        $this->service->import($title, $this->creditsPayload(cast: [
            $this->castEntry(1, 'Dual Role', 'Good Twin', 0),
            $this->castEntry(1, 'Dual Role', 'Evil Twin', 1),
            $this->castEntry(2, 'Supporting', 'Sidekick', 2),
        ]), castLimit: 2);

        $this->assertEqualsCanonicalizing(
            ['Dual Role', 'Supporting'],
            $title->cast()->with('person')->get()->pluck('person.name')->all(),
        );
    }

    public function test_collapses_a_person_credited_in_both_cast_and_crew_as_cast(): void
    {
        $title = $this->movie();

        // TMDB sometimes lists a voice performer in crew[] as well as cast[].
        // Both map to CreditJob::CAST and would target the same conflict row.
        $written = $this->service->import($title, $this->creditsPayload(
            cast: [$this->castEntry(1, 'Voice Actor', 'The Narrator', 0)],
            crew: [$this->crewEntry(1, 'Voice Actor', 'Voice')],
        ));

        $this->assertSame(1, $written);
        $this->assertDatabaseHas('title_person', [
            'person_id' => Person::where('tmdb_id', 1)->sole()->id,
            'job' => CreditJob::CAST->value,
            // Cast wins the collapse, so the character survives.
            'character' => 'The Narrator',
        ]);
    }

    public function test_collapses_two_raw_jobs_that_map_to_the_same_credit_job(): void
    {
        $title = $this->movie();

        $written = $this->service->import($title, $this->creditsPayload(crew: [
            $this->crewEntry(1, 'The Director', 'Director'),
            $this->crewEntry(1, 'The Director', 'Co-Director'),
        ]));

        $this->assertSame(1, $written);
        $this->assertDatabaseCount('title_person', 1);
        $this->assertDatabaseHas('title_person', [
            'person_id' => Person::where('tmdb_id', 1)->sole()->id,
            'job' => CreditJob::DIRECTOR->value,
        ]);
    }

    public function test_the_same_person_can_hold_two_different_jobs_on_one_title(): void
    {
        $title = $this->movie();

        // The flip side of the collapse: (person, job) is the key, so a
        // writer-director legitimately gets two rows.
        $written = $this->service->import($title, $this->creditsPayload(crew: [
            $this->crewEntry(1, 'Auteur', 'Director'),
            $this->crewEntry(1, 'Auteur', 'Screenplay'),
        ]));

        $this->assertSame(2, $written);
        $this->assertEqualsCanonicalizing(
            [CreditJob::DIRECTOR, CreditJob::WRITER],
            $title->credits()->pluck('job')->all(),
        );
        $this->assertDatabaseCount('people', 1);
    }

    // -------------------------------------------------------------------------
    // TV aggregate credits
    // -------------------------------------------------------------------------

    public function test_ranks_tv_cast_by_episode_count_rather_than_billing_order(): void
    {
        $title = $this->show();

        $this->service->import($title, [
            'cast' => [
                // Top-billed but barely in it — billing order is meaningless
                // across a series, so this must lose to the regular.
                $this->aggregateCastEntry(1, 'Guest Star', [
                    ['character' => 'Cameo', 'episode_count' => 2, 'credit_id' => 'c1'],
                ], totalEpisodes: 2, order: 0),
                $this->aggregateCastEntry(2, 'Series Regular', [
                    ['character' => 'The Lead', 'episode_count' => 60, 'credit_id' => 'c2'],
                ], totalEpisodes: 60, order: 5),
            ],
            'crew' => [],
        ], castLimit: 1);

        $imported = $title->cast()->with('person')->get();

        $this->assertSame(1, $imported->count());
        $this->assertSame('Series Regular', $imported->first()->person->name);
        $this->assertSame(60, $imported->first()->episode_count);
    }

    public function test_joins_multiple_roles_played_by_one_tv_actor(): void
    {
        $title = $this->show();

        $this->service->import($title, [
            'cast' => [
                $this->aggregateCastEntry(1, 'Recast', [
                    ['character' => 'Young Bob', 'episode_count' => 10, 'credit_id' => 'c1'],
                    ['character' => 'Old Bob', 'episode_count' => 5, 'credit_id' => 'c2'],
                ], totalEpisodes: 15),
            ],
            'crew' => [],
        ]);

        $this->assertDatabaseHas('title_person', [
            'title_id' => $title->id,
            'character' => 'Young Bob / Old Bob',
            'episode_count' => 15,
        ]);
    }

    public function test_filters_tv_cast_below_the_minimum_episode_threshold(): void
    {
        $title = $this->show();

        $written = $this->service->importFromAggregateCredits($title, [
            'cast' => [
                $this->aggregateCastEntry(1, 'One Episode Wonder', [
                    ['character' => 'Waiter', 'episode_count' => 1, 'credit_id' => 'c1'],
                ], totalEpisodes: 1),
                $this->aggregateCastEntry(2, 'Series Regular', [
                    ['character' => 'The Lead', 'episode_count' => 60, 'credit_id' => 'c2'],
                ], totalEpisodes: 60),
            ],
            'crew' => [],
        ], castLimit: 10, minEpisodesForCast: 5);

        $this->assertSame(1, $written);
        $this->assertDatabaseHas('people', ['tmdb_id' => 2]);
        $this->assertDatabaseMissing('people', ['tmdb_id' => 1]);
    }

    public function test_expands_one_crew_entry_into_a_row_per_job_and_sums_episodes(): void
    {
        $title = $this->show();

        $this->service->import($title, [
            'cast' => [],
            'crew' => [
                $this->aggregateCrewEntry(1, 'Showrunner', [
                    ['job' => 'Director', 'episode_count' => 4, 'credit_id' => 'j1'],
                    // Collapses into the Director row above, summing episodes.
                    ['job' => 'Co-Director', 'episode_count' => 2, 'credit_id' => 'j2'],
                    ['job' => 'Writer', 'episode_count' => 12, 'credit_id' => 'j3'],
                ]),
            ],
        ]);

        $this->assertDatabaseHas('title_person', [
            'job' => CreditJob::DIRECTOR->value,
            'episode_count' => 6,
        ]);
        $this->assertDatabaseHas('title_person', [
            'job' => CreditJob::WRITER->value,
            'episode_count' => 12,
        ]);
        $this->assertDatabaseCount('title_person', 2);
    }

    // -------------------------------------------------------------------------
    // Routing, people, idempotency
    // -------------------------------------------------------------------------

    public function test_import_picks_the_normalizer_from_the_title_type(): void
    {
        // The aggregate shape (nested roles[]) against a MOVIE title yields
        // nothing, because import() routes movies to the flat normalizer. This
        // is what stops a caller from silently importing the wrong shape.
        $movie = $this->movie();

        $written = $this->service->import($movie, [
            'cast' => [
                $this->aggregateCastEntry(1, 'Series Regular', [
                    ['character' => 'The Lead', 'episode_count' => 60, 'credit_id' => 'c1'],
                ], totalEpisodes: 60),
            ],
            'crew' => [],
        ]);

        // The flat normalizer reads cast[].character, which the aggregate shape
        // doesn't have — the person is still imported, just with no character.
        $this->assertSame(1, $written);
        $this->assertDatabaseHas('title_person', [
            'title_id' => $movie->id,
            'character' => null,
            'episode_count' => null,
        ]);
    }

    public function test_upserts_people_with_mapped_gender_and_extra_attributes(): void
    {
        $title = $this->movie();

        $this->service->import($title, $this->creditsPayload(cast: [
            $this->castEntry(1, 'Jane Doe', 'Hero', 0),
        ]));

        $person = Person::where('tmdb_id', 1)->sole();

        $this->assertSame('jane-doe-1', $person->slug);
        // TMDB sends gender as an int; it must land as our enum, not "1".
        $this->assertSame(Gender::FEMALE, $person->gender);
        $this->assertSame('Acting', $person->extra_attributes['known_for_department']);
    }

    public function test_reuses_an_existing_person_rather_than_duplicating_them(): void
    {
        $existing = Person::factory()->create(['tmdb_id' => 1, 'name' => 'Stale Name']);
        $title = $this->movie();

        $this->service->import($title, $this->creditsPayload(cast: [
            $this->castEntry(1, 'Fresh Name', 'Hero', 0),
        ]));

        $this->assertDatabaseCount('people', 1);
        $this->assertSame('Fresh Name', $existing->fresh()->name);
    }

    public function test_reimporting_the_same_credits_updates_in_place(): void
    {
        $title = $this->movie();

        $this->service->import($title, $this->creditsPayload(cast: [
            $this->castEntry(1, 'Lead', 'Old Character', 0),
        ]));
        $this->service->import($title, $this->creditsPayload(cast: [
            $this->castEntry(1, 'Lead', 'Renamed Character', 0),
        ]));

        $this->assertDatabaseCount('title_person', 1);
        $this->assertDatabaseHas('title_person', ['character' => 'Renamed Character']);
    }

    public function test_returns_zero_and_writes_nothing_for_an_empty_payload(): void
    {
        $title = $this->movie();

        $this->assertSame(0, $this->service->import($title, []));
        $this->assertSame(0, $this->service->import($title, $this->creditsPayload()));

        $this->assertDatabaseCount('title_person', 0);
        $this->assertDatabaseCount('people', 0);
    }

    public function test_stores_a_character_list_too_long_for_a_varchar(): void
    {
        $title = $this->show();

        $roles = collect(range(1, 40))
            ->map(fn (int $i) => [
                'character' => "Character Number {$i} With A Padded Name",
                'episode_count' => 3,
                'credit_id' => "c{$i}",
            ])
            ->all();

        $this->service->import($title, [
            'cast' => [$this->aggregateCastEntry(1, 'Voice Cast', $roles, totalEpisodes: 120)],
            'crew' => [],
        ]);

        $character = $title->cast()->sole()->character;

        $this->assertGreaterThan(255, strlen($character));
    }
}
