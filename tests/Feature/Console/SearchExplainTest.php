<?php

namespace Tests\Feature\Console;

use App\Models\Genre;
use App\Models\ProductionCompany;
use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\Search\SearchDocumentBuilder;
use Database\Factories\TitleSearchEmbeddingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchExplainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.llm.providers' => ['openai']]);
        Http::fake(['api.openai.com/*' => fn (Request $request) => str_ends_with($request->url(), '/chat/completions')
            ? Http::response(['choices' => [['message' => ['content' => json_encode([
                'title' => 'Lost Orbit',
                'document' => "Lost Orbit\nAstronauts drift between stars\nGenres: Science Fiction",
            ])]]]])
            : Http::response([
                'data' => collect($request['input'])->map(fn (string $input, int $i) => [
                    'index' => $i,
                    'embedding' => TitleSearchEmbeddingFactory::unitVector(
                        $input === 'galaxy' || str_contains($input, 'Astronauts') ? 3 : 9
                    ),
                ])->all(),
            ])]);
    }

    private function createSearchableTitle(array $overrides = []): Title
    {
        $title = Title::factory()->create($overrides);
        $title->genres()->attach(Genre::factory()->create());
        $title->companies()->attach(ProductionCompany::factory()->create());

        return $title;
    }

    public function test_ranks_each_retriever_and_explains_an_expected_title(): void
    {
        $this->createSearchableTitle(['name' => 'Galaxy Quest', 'release_date' => '1999-12-25']);
        $expected = Title::factory()->create(['name' => 'Space Story', 'overview' => 'Astronauts lost in space.']);
        TitleSearchEmbedding::factory()->for($expected)->pointingAt(3)->create();
        TitleSearchEmbedding::factory()->pointingAt(9)->create();
        Artisan::call('titles:refresh-searchable');

        $this->artisan('search:explain', ['query' => 'galaxy', '--expect' => [$expected->id]])
            ->expectsOutputToContain('Full-text (ts_rank × popularity, higher is better)')
            ->expectsOutputToContain('Galaxy Quest (1999)')
            ->expectsOutputToContain('Trigram (word similarity × popularity, higher is better)')
            ->expectsOutputToContain("Expected: #{$expected->id} Space Story")
            ->expectsOutputToContain('Full-text  not in top 50')
            ->expectsOutputToContain('Semantic   rank 1')
            ->expectsOutputToContain('Cosine similarity 1.0000, exact rank 1 among all embedded titles')
            ->expectsOutputToContain('Retrieved, but closer titles outrank it')
            ->expectsOutputToContain('Astronauts lost in space.')
            ->doesntExpectOutputToContain('HyDE       rank')
            ->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_ranks_titles_near_the_hypothetical_document_for_longer_queries(): void
    {
        $expected = TitleSearchEmbedding::factory()->pointingAt(3)->create();
        TitleSearchEmbedding::factory()->pointingAt(9)->create();

        $this->artisan('search:explain', ['query' => 'crew lost far from home', '--expect' => [$expected->title_id]])
            ->expectsOutputToContain('Hypothetical document (LLM output, document embedded as-is)')
            ->expectsOutputToContain('    Title guess: Lost Orbit')
            ->expectsOutputToContain('    Astronauts drift between stars')
            ->expectsOutputToContain('HyDE (cosine similarity to the hypothetical document, higher is better)')
            ->expectsOutputToContain('Full-text on the title guess (ts_rank × popularity, higher is better)')
            ->expectsOutputToContain('Hybrid + HyDE (rrf score over full-text, trigram, HyDE and the title guess, higher is better)')
            ->expectsOutputToContain('Semantic   rank 2')
            ->expectsOutputToContain('HyDE       rank 1')
            ->expectsOutputToContain('Trigram on the title guess (word similarity × popularity, higher is better)')
            ->expectsOutputToContain('Title text not in top 50')
            ->expectsOutputToContain('Title trgm not in top 50')
            ->expectsOutputToContain('HyDE fused rank 1')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'));
    }

    public function test_explains_why_hyde_is_skipped_for_a_close_title_match(): void
    {
        Title::factory()->create(['name' => 'The Silence of the Lambs']);
        config(['search.fusion' => 'min-max']);

        $this->artisan('search:explain', ['query' => 'the silence of the lambs'])
            ->expectsOutputToContain('Hybrid (min-max score, higher is better)')
            ->expectsOutputToContain('skipped: a title name already matches the query closely, so hydeIds falls back to hybrid')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'));
    }

    public function test_lines_option_embeds_each_document_line_and_marks_the_closest(): void
    {
        $expected = Title::factory()->create([
            'name' => 'Space Story',
            'tagline' => null,
            'overview' => 'Astronauts lost in space.',
        ]);
        TitleSearchEmbedding::factory()->for($expected)->pointingAt(3)->create();

        $this->artisan('search:explain', ['query' => 'galaxy', '--expect' => [$expected->id], '--lines' => true])
            ->expectsOutputToContain('Each document line embedded on its own')
            ->expectsOutputToContain('0.0000  Space Story')
            ->expectsOutputToContain('1.0000  Astronauts lost in space.  ◀ closest')
            ->doesntExpectOutputToContain('Document the embedding is built from')
            ->assertSuccessful();
    }

    public function test_reports_a_far_away_expected_title(): void
    {
        $expected = TitleSearchEmbedding::factory()->pointingAt(9)->create();
        TitleSearchEmbedding::factory()->pointingAt(3)->create();

        $this->artisan('search:explain', ['query' => 'galaxy', '--expect' => [$expected->title_id]])
            ->expectsOutputToContain('Cosine similarity 0.0000, exact rank 2')
            ->expectsOutputToContain('Retrieved, but closer titles outrank it')
            ->assertSuccessful();
    }

    public function test_warns_about_missing_and_stale_embeddings(): void
    {
        $missing = Title::factory()->create();
        $stale = TitleSearchEmbedding::factory()->create(['document_version' => SearchDocumentBuilder::VERSION - 1]);

        $this->artisan('search:explain', ['query' => 'galaxy', '--expect' => [$missing->id, $stale->title_id]])
            ->expectsOutputToContain('No search embedding yet')
            ->expectsOutputToContain('Embedding is stale: built from document v'.(SearchDocumentBuilder::VERSION - 1))
            ->assertSuccessful();
    }

    public function test_flags_adult_and_unknown_titles(): void
    {
        $adult = Title::factory()->create(['is_adult' => true]);

        $this->artisan('search:explain', ['query' => 'galaxy', '--expect' => [$adult->id, 999_999]])
            ->expectsOutputToContain('hidden from guests')
            ->expectsOutputToContain('Expected title #999999 does not exist.')
            ->assertSuccessful();
    }
}
