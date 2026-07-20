<?php

namespace Tests\Feature\Service\KnowledgeGraph;

use App\Domain\Enums\CreditJob;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Person;
use App\Models\Title;
use App\Services\KnowledgeGraph\TitleDocumentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TitleDocumentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function attachCast(Title $title, string $name, int $order): void
    {
        $person = Person::factory()->create(['name' => $name]);
        $title->people()->attach($person->id, ['job' => CreditJob::CAST->value, 'order' => $order]);
    }

    public function test_builds_a_document_with_each_populated_section(): void
    {
        $title = Title::factory()->create(['name' => 'My Movie', 'release_date' => '2020-05-01']);
        $title->genres()->attach(Genre::factory()->create(['name' => 'Action']));
        $title->keywords()->attach(Keyword::factory()->create(['name' => 'heist']));
        $this->attachCast($title, 'Lead Actor', 0);
        $director = Person::factory()->create(['name' => 'Jane Director']);
        $title->people()->attach($director->id, ['job' => CreditJob::DIRECTOR->value, 'order' => 0]);

        $doc = app(TitleDocumentBuilder::class)->build($title->fresh());

        $this->assertStringContainsString('Title: My Movie', $doc);
        $this->assertStringContainsString('Genres: Action', $doc);
        $this->assertStringContainsString('Keywords: heist', $doc);
        $this->assertStringContainsString('Cast: Lead Actor', $doc);
        $this->assertStringContainsString('Directors: Jane Director', $doc);
        $this->assertStringContainsString('Year: 2020', $doc);
        $this->assertStringNotContainsString('2020-05-01', $doc); // year only, no full timestamp
    }

    public function test_caps_cast_at_billing_order_five(): void
    {
        $title = Title::factory()->create();
        foreach (['A' => 0, 'B' => 5, 'FarDown' => 6] as $name => $order) {
            $this->attachCast($title, $name, $order);
        }

        $doc = app(TitleDocumentBuilder::class)->build($title->fresh());

        $this->assertStringContainsString('A', $doc);
        $this->assertStringContainsString('B', $doc);          // order 5 kept
        $this->assertStringNotContainsString('FarDown', $doc); // order 6 dropped
    }

    public function test_omits_empty_sections(): void
    {
        $title = Title::factory()->create(['name' => 'Bare', 'release_date' => null, 'collection_id' => null]);

        $doc = app(TitleDocumentBuilder::class)->build($title->fresh());

        $this->assertSame('Title: Bare', $doc);
    }
}
