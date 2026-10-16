<?php

namespace Tests\Feature\Service\Search;

use App\Domain\Enums\CreditJob;
use App\Domain\Enums\TitleType;
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
        return app(SearchDocumentBuilder::class)->build($title->fresh(['genres', 'keywords', 'collection', 'people']));
    }

    public function test_builds_a_document_with_overview_tagline_and_metadata(): void
    {
        $title = Title::factory()->create([
            'name' => 'The Martian',
            'original_name' => 'The Martian',
            'type' => TitleType::MOVIE->value,
            'overview' => 'An astronaut is stranded on Mars.',
            'tagline' => 'Bring him home.',
            'release_date' => '2015-10-02',
        ]);
        $title->genres()->attach(Genre::factory()->create(['name' => 'Science Fiction']));
        $title->keywords()->attach(Keyword::factory()->create(['name' => 'mars']));
        $this->attachPerson($title, 'Matt Damon', CreditJob::CAST);
        $this->attachPerson($title, 'Ridley Scott', CreditJob::DIRECTOR);
        $this->attachPerson($title, 'Drew Goddard', CreditJob::WRITER);

        $document = $this->build($title);

        $this->assertStringContainsString('Title: The Martian', $document);
        $this->assertStringContainsString('Type: movie', $document);
        $this->assertStringContainsString('Tagline: Bring him home.', $document);
        $this->assertStringContainsString('Overview: An astronaut is stranded on Mars.', $document);
        $this->assertStringContainsString('Genres: Science Fiction', $document);
        $this->assertStringContainsString('Keywords: mars', $document);
        $this->assertStringContainsString('Cast: Matt Damon', $document);
        $this->assertStringContainsString('Directors: Ridley Scott', $document);
        $this->assertStringContainsString('Writers: Drew Goddard', $document);
        $this->assertStringContainsString('Year: 2015', $document);
        $this->assertStringNotContainsString('Original title', $document);
    }

    public function test_includes_original_title_when_it_differs(): void
    {
        $title = Title::factory()->create(['name' => 'Parasite', 'original_name' => '기생충']);

        $this->assertStringContainsString('Original title: 기생충', $this->build($title));
    }

    public function test_omits_empty_sections(): void
    {
        $title = Title::factory()->create([
            'name' => 'Bare',
            'original_name' => 'Bare',
            'overview' => null,
            'tagline' => '',
            'release_date' => null,
            'collection_id' => null,
        ]);

        $this->assertSame("Title: Bare\nType: movie", $this->build($title));
    }

    public function test_ignores_other_crew_jobs_and_caps_cast_order(): void
    {
        $title = Title::factory()->create();
        $this->attachPerson($title, 'Lead', CreditJob::CAST, 0);
        $this->attachPerson($title, 'Fifth', CreditJob::CAST, 5);
        $this->attachPerson($title, 'FarDown', CreditJob::CAST, 6);
        $this->attachPerson($title, 'Hans Composer', CreditJob::COMPOSER);
        $this->attachPerson($title, 'Art Person', CreditJob::ART_DIRECTOR);

        $document = $this->build($title);

        $this->assertStringContainsString('Cast: Lead, Fifth', $document);
        $this->assertStringNotContainsString('FarDown', $document);
        $this->assertStringNotContainsString('Hans Composer', $document);
        $this->assertStringNotContainsString('Art Person', $document);
    }
}
