<?php

namespace Tests\Feature\Service\Search;

use App\Domain\Enums\CreditJob;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Person;
use App\Models\Title;
use App\Services\Search\SearchDocumentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchDocumentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function attachPerson(Title $title, string $name, CreditJob $job, int $order = 0): void
    {
        $title->people()->attach(Person::factory()->create(['name' => $name])->id, ['job' => $job->value, 'order' => $order]);
    }

    private function build(Title $title): string
    {
        return app(SearchDocumentBuilder::class)->build(
            Title::withoutGlobalScopes()->with(SearchDocumentBuilder::relations())->find($title->id)
        );
    }

    public function test_builds_a_narrative_first_document(): void
    {
        $title = Title::factory()->create([
            'name' => 'The Martian',
            'tagline' => 'Bring him home.',
            'overview' => 'An astronaut is stranded on Mars.',
        ]);
        $title->genres()->attach(Genre::factory()->create(['name' => 'Science Fiction']));
        $title->keywords()->attach(Keyword::factory()->create(['name' => 'mars']));
        $this->attachPerson($title, 'Ridley Scott', CreditJob::DIRECTOR);
        $this->attachPerson($title, 'Drew Goddard', CreditJob::WRITER);
        $this->attachPerson($title, 'Matt Damon', CreditJob::CAST);

        $lines = explode("\n", $this->build($title));

        $this->assertSame('The Martian: Bring him home.', $lines[0]);
        $this->assertSame('An astronaut is stranded on Mars.', $lines[1]);
        $this->assertSame('Genres: Science Fiction', $lines[2]);
        $this->assertStringContainsString('mars', $lines[3]);
        $this->assertSame(['Directed by Ridley Scott.', 'Written by Drew Goddard.', 'Starring Matt Damon.'], array_slice($lines, 4));
    }

    public function test_omits_empty_sections(): void
    {
        $title = Title::factory()->create(['name' => 'Bare', 'tagline' => null, 'overview' => null]);

        $this->assertSame('Bare', $this->build($title));
    }

    public function test_limits_credits_and_keywords_in_billing_order(): void
    {
        $title = Title::factory()->create();
        foreach (range(6, 0) as $order) {
            $this->attachPerson($title, "Actor {$order}", CreditJob::CAST, $order);
        }
        foreach (['First', 'Second', 'Third'] as $order => $name) {
            $this->attachPerson($title, "{$name} Director", CreditJob::DIRECTOR, $order);
        }
        $this->attachPerson($title, 'Hans Composer', CreditJob::COMPOSER);
        $title->keywords()->attach(Keyword::factory(12)->create());

        $document = $this->build($title);

        $this->assertStringContainsString('Starring Actor 0, Actor 1, Actor 2, Actor 3, Actor 4.', $document);
        $this->assertStringContainsString('Directed by First Director, Second Director.', $document);
        $this->assertStringNotContainsString('Third Director', $document);
        $this->assertStringNotContainsString('Hans Composer', $document);
        $themes = collect(explode("\n", $document))->first(fn (string $line) => str_starts_with($line, 'Themes:'));
        $this->assertCount(10, explode(', ', trim(substr($themes, strlen('Themes:')))));
    }
}
