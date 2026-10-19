<?php

namespace Tests\Feature\Service\Search;

use App\Domain\Enums\CreditJob;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Person;
use App\Models\Title;
use App\Services\Search\HypotheticalDocumentGenerator;
use App\Services\Search\SearchDocumentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HypotheticalDocumentGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.llm.providers' => ['openai']]);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'WALL-E: After 700 years of doing what he was built for...']]],
        ])]);
    }

    private function generator(): HypotheticalDocumentGenerator
    {
        return app(HypotheticalDocumentGenerator::class);
    }

    public function test_generates_a_document_for_the_normalized_query(): void
    {
        $this->assertSame('WALL-E: After 700 years of doing what he was built for...', $this->generator()->generate('  Lonely Robot '));

        Http::assertSent(fn (Request $request) => $request['messages'][1] === ['role' => 'user', 'content' => 'lonely robot']);
    }

    public function test_caches_documents_per_normalized_query(): void
    {
        $this->generator()->generate('Lonely Robot');
        $this->generator()->generate('  lonely robot ');

        Http::assertSentCount(1);
    }

    public function test_prompt_asks_for_every_section_of_the_search_document(): void
    {
        $title = Title::factory()->create(['name' => 'WALL-E', 'tagline' => 'After 700 years...', 'overview' => 'A robot cleans up Earth.']);
        $title->genres()->attach(Genre::factory()->create());
        $title->keywords()->attach(Keyword::factory()->create());
        foreach ([CreditJob::DIRECTOR, CreditJob::WRITER, CreditJob::CAST] as $job) {
            $title->people()->attach(Person::factory()->create()->id, ['job' => $job->value, 'order' => 0]);
        }
        $document = app(SearchDocumentBuilder::class)->build(
            Title::withoutGlobalScopes()->with(SearchDocumentBuilder::relations())->find($title->id)
        );
        $sectionLabels = collect(explode("\n", $document))->slice(2)->map(fn (string $line) => strtok($line, ' '));

        $this->generator()->generate('lonely robot');

        $this->assertCount(5, $sectionLabels);
        Http::assertSent(fn (Request $request) => $sectionLabels->every(
            fn (string $label) => str_contains($request['messages'][0]['content'], $label)
        ));
    }
}
