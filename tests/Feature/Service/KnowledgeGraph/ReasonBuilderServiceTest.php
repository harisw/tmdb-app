<?php

namespace Tests\Feature\Service\KnowledgeGraph;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Title;
use App\Services\KnowledgeGraph\ReasonBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReasonBuilderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_reasons_by_weight_descending(): void
    {
        $genre = Genre::factory()->create(['name' => 'Action']);
        $keyword = Keyword::factory()->create(['name' => 'heist']);
        $a = Title::factory()->create();
        $b = Title::factory()->create();
        foreach ([$a, $b] as $t) {
            $t->genres()->attach($genre);     // weight 0.3
            $t->keywords()->attach($keyword); // weight 0.4
        }

        $reasons = app(ReasonBuilderService::class)->build($a->fresh(), $b->fresh(), 0.8);

        // semantic (0.8) > shared_keywords (0.4) > shared_genres (0.3)
        $this->assertSame(
            ['semantic_similarity', 'shared_keywords', 'shared_genres'],
            array_column($reasons, 'type'),
        );
        $this->assertContains('80.0% match', $reasons[0]['values']);
    }

    public function test_returns_only_the_semantic_reason_when_nothing_is_shared(): void
    {
        $a = Title::factory()->create();
        $b = Title::factory()->create();

        $reasons = app(ReasonBuilderService::class)->build($a->fresh(), $b->fresh(), 0.5);

        $this->assertCount(1, $reasons);
        $this->assertSame('semantic_similarity', $reasons[0]['type']);
    }
}
