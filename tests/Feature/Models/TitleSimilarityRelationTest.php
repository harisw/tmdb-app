<?php

namespace Tests\Feature\Models;

use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TitleSimilarityRelationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    private function attachSimilar(Title $title, Title $similar, float $score, array $reasons = []): void
    {
        $title->similarTitles()->attach($similar->id, [
            'score' => $score,
            'reasons' => json_encode($reasons),
        ]);
    }

    public function test_similar_titles_returns_attached_neighbours(): void
    {
        $title = Title::factory()->create();
        $a = Title::factory()->create();
        $b = Title::factory()->create();

        $this->attachSimilar($title, $a, 0.8);
        $this->attachSimilar($title, $b, 0.6);

        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $title->similarTitles->pluck('id')->all()
        );
    }

    public function test_similar_titles_are_ordered_by_score_descending(): void
    {
        $title = Title::factory()->create();
        $low = Title::factory()->create();
        $high = Title::factory()->create();
        $mid = Title::factory()->create();

        $this->attachSimilar($title, $low, 0.3);
        $this->attachSimilar($title, $high, 0.95);
        $this->attachSimilar($title, $mid, 0.6);

        $this->assertSame(
            [$high->id, $mid->id, $low->id],
            $title->similarTitles->pluck('id')->all()
        );
    }

    public function test_similar_titles_expose_pivot_score_and_reasons(): void
    {
        $title = Title::factory()->create();
        $similar = Title::factory()->create();

        $this->attachSimilar($title, $similar, 0.77, [['type' => 'semantic', 'values' => ['77% match']]]);

        $pivot = $title->similarTitles->first()->pivot;

        $this->assertEqualsWithDelta(0.77, (float) $pivot->score, 0.0001);
        $this->assertSame(
            [['type' => 'semantic', 'values' => ['77% match']]],
            json_decode($pivot->reasons, true)
        );
    }

    public function test_relationship_matches_the_title_id_side_only(): void
    {
        $title = Title::factory()->create();
        $similar = Title::factory()->create();

        // A single-direction row surfaces only on the title_id side.
        $this->attachSimilar($title, $similar, 0.9);

        $this->assertTrue($title->similarTitles->contains($similar));
        $this->assertFalse($similar->fresh()->similarTitles->contains($title));

        // MaterializeTitleSimilaritiesJob writes both directions, so a mirrored
        // row makes the relationship resolve from either side.
        $this->attachSimilar($similar, $title, 0.9);

        $this->assertTrue($similar->fresh()->similarTitles->contains($title));
    }
}
