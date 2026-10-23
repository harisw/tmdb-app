<?php

namespace Tests\Feature\Service\KnowledgeGraph;

use App\Data\DTOs\SimilarityReasonData;
use App\Domain\Enums\CreditJob;
use App\Domain\Enums\ReasonType;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Person;
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
            $t->genres()->attach($genre);     // weight 0.2
            $t->keywords()->attach($keyword); // weight 0.4
        }

        $reasons = app(ReasonBuilderService::class)->build($a->fresh(), $b->fresh(), 0.8);

        // semantic (0.8) > shared_keywords (0.4) > shared_genres (0.2)
        $this->assertSame(
            [ReasonType::SEMANTIC_SIMILARITY, ReasonType::SHARED_KEYWORDS, ReasonType::SHARED_GENRES],
            array_column($reasons, 'type'),
        );
        $this->assertContains('80.0% match', $reasons[0]->values);
    }

    public function test_returns_only_the_semantic_reason_when_nothing_is_shared(): void
    {
        $a = Title::factory()->create();
        $b = Title::factory()->create();

        $reasons = app(ReasonBuilderService::class)->build($a->fresh(), $b->fresh(), 0.5);

        $this->assertCount(1, $reasons);
        $this->assertSame(ReasonType::SEMANTIC_SIMILARITY, $reasons[0]->type);
    }

    public function test_reports_shared_crew_members_per_job(): void
    {
        $director = Person::factory()->create(['name' => 'Denis Villeneuve']);
        $composer = Person::factory()->create(['name' => 'Hans Zimmer']);
        $a = Title::factory()->create();
        $b = Title::factory()->create();
        $a->people()->attach($director, ['job' => CreditJob::DIRECTOR->value, 'order' => 0]);
        $b->people()->attach($director, ['job' => CreditJob::DIRECTOR->value, 'order' => 0]);
        $a->people()->attach($composer, ['job' => CreditJob::COMPOSER->value, 'order' => 0]);
        $b->people()->attach($composer, ['job' => CreditJob::WRITER->value, 'order' => 0]);

        $reasons = $this->build($a, $b);

        $this->assertSame(['Denis Villeneuve'], $this->valuesOf($reasons, ReasonType::SHARED_DIRECTOR));
        $this->assertNull($this->valuesOf($reasons, ReasonType::SHARED_COMPOSER));
        $this->assertNull($this->valuesOf($reasons, ReasonType::SHARED_WRITER));
    }

    public function test_reports_shared_cast_members(): void
    {
        $actor = Person::factory()->create(['name' => 'Rebecca Ferguson']);
        $a = Title::factory()->create();
        $b = Title::factory()->create();
        $a->people()->attach($actor, ['job' => CreditJob::CAST->value, 'order' => 1]);
        $b->people()->attach($actor, ['job' => CreditJob::CAST->value, 'order' => 3]);

        $this->assertSame(['Rebecca Ferguson'], $this->valuesOf($this->build($a, $b), ReasonType::SHARED_CASTS));
    }

    public function test_does_not_treat_different_people_with_the_same_name_as_shared(): void
    {
        $a = Title::factory()->create();
        $b = Title::factory()->create();
        $a->people()->attach(Person::factory()->create(['name' => 'John Smith']), ['job' => CreditJob::CAST->value, 'order' => 0]);
        $b->people()->attach(Person::factory()->create(['name' => 'John Smith']), ['job' => CreditJob::CAST->value, 'order' => 0]);

        $this->assertNull($this->valuesOf($this->build($a, $b), ReasonType::SHARED_CASTS));
    }

    public function test_ignores_keywords_excluded_from_similarity(): void
    {
        $generic = Keyword::factory()->create(['name' => 'based on novel', 'use_in_similarity' => false]);
        $specific = Keyword::factory()->create(['name' => 'heist']);
        $a = Title::factory()->create();
        $b = Title::factory()->create();
        foreach ([$a, $b] as $t) {
            $t->keywords()->attach([$generic->id, $specific->id]);
        }

        $this->assertSame(['heist'], $this->valuesOf($this->build($a, $b), ReasonType::SHARED_KEYWORDS));
    }

    /**
     * @return list<SimilarityReasonData>
     */
    private function build(Title $a, Title $b): array
    {
        return app(ReasonBuilderService::class)->build($a->fresh(), $b->fresh(), 0.8);
    }

    private function valuesOf(array $reasons, ReasonType $type): ?array
    {
        return collect($reasons)->firstWhere('type', $type)?->values;
    }
}
