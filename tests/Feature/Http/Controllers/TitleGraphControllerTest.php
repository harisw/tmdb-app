<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TitleGraphControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Bus::fake();
    }

    private function attachSimilar(Title $title, Title $similar, float $score, array $reasons = []): void
    {
        $title->similarTitles()->attach($similar->id, [
            'score' => $score,
            'reasons' => json_encode($reasons),
        ]);
    }

    public function test_graph_page_renders_with_slug_prop(): void
    {
        $this->get('/graph?title_slug=some-title')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleGraph')
                ->where('slug', 'some-title')
            );
    }

    public function test_graph_page_slug_is_null_without_query(): void
    {
        $this->get('/graph')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleGraph')
                ->where('slug', null)
            );
    }

    public function test_neighbors_requires_title_slug(): void
    {
        $this->getJson('/graph/neighbors')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('title_slug');
    }

    public function test_neighbors_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/graph/neighbors?title_slug=does-not-exist')
            ->assertNotFound();
    }

    public function test_neighbors_returns_node_nodes_and_links(): void
    {
        $title = Title::factory()->create(['slug' => 'root-title']);
        $a = Title::factory()->create();
        $b = Title::factory()->create();

        $this->attachSimilar($title, $a, 0.8);
        $this->attachSimilar($title, $b, 0.6);

        $this->getJson('/graph/neighbors?title_slug=root-title')
            ->assertOk()
            ->assertJsonPath('node.id', $title->id)
            ->assertJsonPath('node.slug', 'root-title')
            ->assertJsonCount(2, 'nodes')
            ->assertJsonCount(2, 'links')
            ->assertJsonPath('links.0.source', $title->id);
    }

    public function test_neighbors_excludes_scores_below_threshold(): void
    {
        $title = Title::factory()->create(['slug' => 'threshold-title']);
        $keep = Title::factory()->create();
        $drop = Title::factory()->create();

        $this->attachSimilar($title, $keep, 0.5);
        $this->attachSimilar($title, $drop, 0.3); // below MIN_SCORE (0.4)

        $this->getJson('/graph/neighbors?title_slug=threshold-title')
            ->assertOk()
            ->assertJsonCount(1, 'nodes')
            ->assertJsonPath('nodes.0.id', $keep->id);
    }

    public function test_neighbors_are_ordered_by_score_descending(): void
    {
        $title = Title::factory()->create(['slug' => 'ordered-title']);
        $low = Title::factory()->create();
        $high = Title::factory()->create();

        $this->attachSimilar($title, $low, 0.5);
        $this->attachSimilar($title, $high, 0.9);

        $this->getJson('/graph/neighbors?title_slug=ordered-title')
            ->assertOk()
            ->assertJsonPath('nodes.0.id', $high->id)
            ->assertJsonPath('nodes.1.id', $low->id);
    }

    public function test_neighbors_are_capped_at_the_limit(): void
    {
        $title = Title::factory()->create(['slug' => 'busy-title']);

        Title::factory(16)->create()->each(
            fn (Title $t) => $this->attachSimilar($title, $t, 0.9)
        );

        $this->getJson('/graph/neighbors?title_slug=busy-title')
            ->assertOk()
            ->assertJsonCount(8, 'nodes')
            ->assertJsonCount(8, 'links');
    }

    public function test_neighbors_decode_reasons_into_an_array(): void
    {
        $title = Title::factory()->create(['slug' => 'reasoned-title']);
        $similar = Title::factory()->create();

        $reasons = [['type' => 'semantic_similarity', 'values' => ['90% match']]];
        $this->attachSimilar($title, $similar, 0.9, $reasons);

        $this->getJson('/graph/neighbors?title_slug=reasoned-title')
            ->assertOk()
            ->assertJsonPath('links.0.reasons', $reasons)
            ->assertJsonPath('links.0.score', 0.9);
    }

    public function test_neighbors_excludes_adult_titles(): void
    {
        $title = Title::factory()->create(['slug' => 'sfw-title']);
        $safe = Title::factory()->create(['is_adult' => false]);
        $adult = Title::factory()->create(['is_adult' => true]);

        $this->attachSimilar($title, $safe, 0.9);
        $this->attachSimilar($title, $adult, 0.9);

        $this->getJson('/graph/neighbors?title_slug=sfw-title')
            ->assertOk()
            ->assertJsonCount(1, 'nodes')
            ->assertJsonPath('nodes.0.id', $safe->id);
    }
}
