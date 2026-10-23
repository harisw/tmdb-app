<?php

namespace Tests\Feature\Models;

use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TitleSimilarityRelationTest extends TestCase
{
    use RefreshDatabase;

    private function attachSimilar(Title $title, Title $similar, float $score, array $reasons = []): void
    {
        $title->similarTitles()->attach($similar->id, [
            'score' => $score,
            'reasons' => $reasons,
        ]);
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
            $pivot->reasons
        );
    }
}
