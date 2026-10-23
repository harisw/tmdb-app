<?php

namespace Tests\Feature\Service\KnowledgeGraph;

use App\Models\Keyword;
use App\Models\Title;
use App\Models\User;
use App\Services\KnowledgeGraph\ConstellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ConstellationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConstellationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        config(['features.knowledge_graph.min_score' => 0.5]);
        $this->service = app(ConstellationService::class);
    }

    private function attachSimilar(Title $title, Title $similar, float $score, array $reasons = []): void
    {
        $title->similarTitles()->attach($similar->id, ['score' => $score, 'reasons' => $reasons]);
    }

    /**
     * @return Collection<int, Title>
     */
    private function seedWithNeighbours(int $popularity, int $count, float $score = 0.8): Collection
    {
        $seed = Title::factory()->create(['popularity' => $popularity]);
        $neighbours = Title::factory($count)->create(['popularity' => 1]);
        $neighbours->each(fn (Title $neighbour) => $this->attachSimilar($seed, $neighbour, $score));

        return collect([$seed])->merge($neighbours);
    }

    public function test_it_returns_null_when_no_title_has_enough_neighbours(): void
    {
        $this->seedWithNeighbours(popularity: 100, count: 3);

        $this->assertNull($this->service->build());
    }

    public function test_it_seeds_from_the_most_popular_title_with_enough_neighbours(): void
    {
        $this->seedWithNeighbours(popularity: 500, count: 3);
        [$seed] = $this->seedWithNeighbours(popularity: 100, count: 4);
        $neighbour = Title::factory()->create();
        $this->attachSimilar($seed, $neighbour, 0.9, [['type' => 'shared_genres', 'values' => ['Drama'], 'weight' => 1]]);

        $graph = $this->service->build();

        $this->assertSame($seed->id, $graph['seed']);
        $this->assertSame(1, $graph['seedCount']);
        $this->assertSame(0, collect($graph['nodes'])->firstWhere('id', $seed->id)['hop']);
        $this->assertCount(5, collect($graph['nodes'])->where('hop', 1));
        $this->assertSame(
            [['type' => 'shared_genres', 'values' => ['Drama'], 'weight' => 1]],
            collect($graph['links'])->firstWhere('target', $neighbour->id)['reasons'],
        );
    }

    public function test_neighbours_below_the_minimum_score_are_left_out(): void
    {
        [$seed] = $this->seedWithNeighbours(popularity: 100, count: 4);
        $weak = Title::factory()->create();
        $this->attachSimilar($seed, $weak, 0.2);

        $graph = $this->service->build();

        $this->assertNotContains($weak->id, collect($graph['nodes'])->pluck('id'));
    }

    public function test_it_adds_two_second_hop_titles_per_neighbour_and_dedupes_cross_links(): void
    {
        [$seed, $first, $second] = $this->seedWithNeighbours(popularity: 100, count: 4);
        $farTitles = Title::factory(3)->create();
        $farTitles->each(fn (Title $far, int $i) => $this->attachSimilar($first, $far, 0.9 - $i / 10));
        $this->attachSimilar($first, $second, 0.7);
        $this->attachSimilar($second, $first, 0.7);
        $this->attachSimilar($first, $seed, 0.8);

        $graph = $this->service->build();
        $nodes = collect($graph['nodes']);

        $this->assertSame([$farTitles[0]->id, $farTitles[1]->id], $nodes->where('hop', 2)->pluck('id')->values()->all());
        $this->assertCount(1, collect($graph['links'])->filter(fn (array $link) => collect([$link['source'], $link['target']])
            ->sort()->values()->all() === collect([$first->id, $second->id])->sort()->values()->all()));
        $this->assertCount(1, collect($graph['links'])->filter(fn (array $link) => collect([$link['source'], $link['target']])
            ->sort()->values()->all() === collect([$seed->id, $first->id])->sort()->values()->all()));
    }

    public function test_it_caps_the_graph_at_the_node_limit(): void
    {
        $titles = $this->seedWithNeighbours(popularity: 100, count: 8);
        $titles->slice(1)->each(fn (Title $neighbour) => Title::factory(3)->create()
            ->each(fn (Title $far) => $this->attachSimilar($neighbour, $far, 0.9)));

        $graph = $this->service->build();

        $this->assertCount(ConstellationService::MAX_NODES, $graph['nodes']);
    }

    public function test_seed_index_picks_the_next_most_popular_seed_and_wraps(): void
    {
        [$first] = $this->seedWithNeighbours(popularity: 200, count: 4);
        [$second] = $this->seedWithNeighbours(popularity: 100, count: 4);

        $this->assertSame($second->id, $this->service->build(1)['seed']);
        $this->assertSame($first->id, $this->service->build(2)['seed']);
        $this->assertSame(0, $this->service->build(2)['seedIndex']);
    }

    public function test_cached_graphs_are_not_shared_with_users_who_avoid_a_neighbour(): void
    {
        [, $avoided] = $this->seedWithNeighbours(popularity: 100, count: 5);
        $zombie = Keyword::factory()->create();
        $avoided->keywords()->attach($zombie->id);

        $this->assertContains($avoided->id, collect($this->service->build()['nodes'])->pluck('id'));

        $user = User::factory()->create();
        $user->avoidedKeywords()->attach($zombie->id);
        $this->actingAs($user);

        $this->assertNotContains($avoided->id, collect($this->service->build()['nodes'])->pluck('id'));
    }
}
