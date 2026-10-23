<?php

namespace Tests\Feature\Service\Search;

use App\Domain\Enums\CreditJob;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Person;
use App\Models\Title;
use App\Services\Llm\LlmUnavailableException;
use App\Services\Search\HypotheticalDocumentGenerator;
use App\Services\Search\SearchDocumentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use UnexpectedValueException;

class HypotheticalDocumentGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private string $completion;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.llm.providers' => ['openai']]);
        $this->completion = json_encode(['title' => ' WALL·E ', 'document' => "WALL·E\nA robot cleans up an abandoned Earth.\n"]);
        Http::fake(['api.openai.com/v1/chat/completions' => fn () => Http::response([
            'choices' => [['message' => ['content' => $this->completion]]],
        ])]);
    }

    private function generator(): HypotheticalDocumentGenerator
    {
        return app(HypotheticalDocumentGenerator::class);
    }

    public function test_generates_a_document_and_title_guess_for_the_normalized_query(): void
    {
        $hypothesis = $this->generator()->generate('  Lonely Robot ');

        $this->assertSame('WALL·E', $hypothesis->title);
        $this->assertSame("WALL·E\nA robot cleans up an abandoned Earth.", $hypothesis->document);
        Http::assertSent(fn (Request $request) => $request['messages'][1] === ['role' => 'user', 'content' => 'lonely robot']);
    }

    public function test_requests_a_strict_json_schema_response(): void
    {
        $this->generator()->generate('lonely robot');

        Http::assertSent(fn (Request $request) => $request['response_format']['type'] === 'json_schema'
            && $request['response_format']['json_schema']['strict'] === true
            && $request['response_format']['json_schema']['schema']['required'] === ['title', 'document']);
    }

    public function test_returns_no_title_when_the_llm_cannot_name_one(): void
    {
        $this->completion = json_encode(['title' => null, 'document' => 'A hand-drawn fantasy about a moving castle.']);

        $this->assertNull($this->generator()->generate('hayao miyazaki films')->title);
    }

    public function test_throws_without_caching_when_the_llm_reply_is_not_json(): void
    {
        $this->completion = '{"title": "WALL·E", "docu';

        try {
            $this->generator()->generate('lonely robot');
            $this->fail('Expected the generator to throw.');
        } catch (LlmUnavailableException) {
            $this->completion = json_encode(['title' => 'WALL·E', 'document' => 'WALL·E']);
            $this->assertSame('WALL·E', $this->generator()->generate('lonely robot')->document);
        }
    }

    public function test_throws_without_caching_when_the_json_has_no_document(): void
    {
        $this->completion = json_encode(['title' => 'WALL·E', 'document' => '']);

        try {
            $this->generator()->generate('lonely robot');
            $this->fail('Expected the generator to throw.');
        } catch (UnexpectedValueException) {
            $this->completion = json_encode(['title' => 'WALL·E', 'document' => 'WALL·E']);
            $this->assertSame('WALL·E', $this->generator()->generate('lonely robot')->document);
        }
    }

    public function test_caches_documents_per_normalized_query(): void
    {
        $this->generator()->generate('Lonely Robot');
        $this->generator()->generate('  lonely robot ');

        Http::assertSentCount(1);
    }

    public function test_prompt_asks_for_every_section_of_the_search_document(): void
    {
        $title = Title::factory()->create(['name' => 'WALL-E', 'overview' => 'A robot cleans up Earth.']);
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
        ) && ! str_contains($request['messages'][0]['content'], 'tagline'));
    }
}
