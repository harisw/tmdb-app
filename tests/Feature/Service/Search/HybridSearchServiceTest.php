<?php

namespace Tests\Feature\Service\Search;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\ProductionCompany;
use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\Search\HybridSearchService;
use Database\Factories\TitleSearchEmbeddingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HybridSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): HybridSearchService
    {
        return app(HybridSearchService::class);
    }

    private function fakeQueryEmbedding(int $dimension): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'data' => [['index' => 0, 'embedding' => TitleSearchEmbeddingFactory::unitVector($dimension)]],
        ])]);
    }

    /**
     * @param  array<int, float>  $weights
     * @return array<int, float>
     */
    private function vector(array $weights): array
    {
        return array_replace(array_fill(0, TitleSearchEmbedding::DIMENSIONS, 0.0), $weights);
    }

    /**
     * @param  array<int, float>  $queryVector
     * @param  array<int, float>  $documentVector
     */
    private function fakeHyde(string $document, array $queryVector, array $documentVector, ?string $title = null): void
    {
        config(['services.llm.providers' => ['openai']]);
        Http::fake(fn (Request $request) => str_ends_with($request->url(), '/chat/completions')
            ? Http::response(['choices' => [['message' => ['content' => json_encode(['title' => $title, 'document' => $document])]]]])
            : Http::response(['data' => [[
                'index' => 0,
                'embedding' => $request['input'] === [$document] ? $documentVector : $queryVector,
            ]]]));
    }

    private function createSearchableTitle(array $overrides = []): Title
    {
        $title = Title::factory()->create($overrides);
        $title->genres()->attach(Genre::factory()->create());
        $title->companies()->attach(ProductionCompany::factory()->create());

        return $title;
    }

    public function test_fused_scores_ranks_ids_found_by_both_lists_first(): void
    {
        $fused = $this->service()->fusedScores([collect([1 => 0.9, 2 => 0.5, 3 => 0.1]), collect([1 => 3.0, 3 => 2.0, 4 => 1.0])])->keys();

        $this->assertSame([1, 3, 2, 4], $fused->all());
    }

    public function test_fused_scores_use_the_configured_fusion_method(): void
    {
        $lists = [collect([1 => 1.0, 2 => 0.9, 3 => 0.0]), collect([2 => 1.0, 3 => 0.9, 4 => 0.0])];

        config(['search.fusion' => 'rrf']);
        $this->assertSame([2, 3, 1, 4], $this->service()->fusedScores($lists)->keys()->all());

        config(['search.fusion' => 'min-max']);
        $this->assertSame([2, 1, 3, 4], $this->service()->fusedScores($lists)->keys()->all());
    }

    public function test_full_text_matches_rank_popular_titles_above_equal_text_matches(): void
    {
        $unknown = $this->createSearchableTitle(['name' => 'The Dark Knight', 'popularity' => 0]);
        $popular = $this->createSearchableTitle(['name' => 'The Dark Knight', 'popularity' => 150]);
        $obscure = $this->createSearchableTitle(['name' => 'The Dark Knight', 'popularity' => 2]);
        Artisan::call('titles:refresh-searchable');

        $this->assertSame([$popular->id, $obscure->id, $unknown->id], $this->service()->lexicalMatches('the dark knight')->fullText->keys()->all());
    }

    public function test_semantic_scores_are_cosine_similarities_closest_first(): void
    {
        $this->fakeQueryEmbedding(3);
        $far = TitleSearchEmbedding::factory()->pointingAt(7)->create();
        $near = TitleSearchEmbedding::factory()->pointingAt(3)->create();

        $this->assertEqualsWithDelta([$near->title_id => 1.0, $far->title_id => 0.0], $this->service()->semanticScores('lonely robot')->all(), 0.001);
    }

    public function test_query_embeddings_are_cached_per_normalized_query(): void
    {
        $this->fakeQueryEmbedding(3);
        TitleSearchEmbedding::factory()->create();

        $this->service()->semanticScores('Lonely Robot');
        $this->service()->semanticScores('  lonely robot ');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['input'] === ['lonely robot']
            && $request['dimensions'] === TitleSearchEmbedding::DIMENSIONS);
    }

    public function test_hybrid_ids_blend_full_text_and_semantic_results(): void
    {
        $this->fakeQueryEmbedding(3);
        $textMatch = $this->createSearchableTitle(['name' => 'Galaxy Quest']);
        $semanticMatch = TitleSearchEmbedding::factory()->pointingAt(3)->create();
        Artisan::call('titles:refresh-searchable');

        $ids = $this->service()->hybridIds('galaxy');

        $this->assertContains($textMatch->id, $ids);
        $this->assertContains($semanticMatch->title_id, $ids);
    }

    public function test_hybrid_ids_fall_back_to_lexical_search_when_openai_fails(): void
    {
        Http::fake(['api.openai.com/*' => Http::response('server error', 500)]);
        $textMatch = $this->createSearchableTitle(['name' => 'Galaxy Quest']);
        $typoMatch = Title::factory()->create(['name' => 'Interstellar']);
        TitleSearchEmbedding::factory()->create();
        Artisan::call('titles:refresh-searchable');

        $this->assertSame([$textMatch->id], $this->service()->hybridIds('galaxy')->all());
        $this->assertSame([$typoMatch->id], $this->service()->hybridIds('intersteller')->all());
    }

    public function test_trigram_matches_misspelled_titles(): void
    {
        $match = Title::factory()->create(['name' => 'The Shawshank Redemption']);
        Title::factory()->create(['name' => 'Galaxy Quest']);

        $this->assertSame([$match->id], $this->service()->lexicalMatches('shawshenk redemption')->trigram->keys()->all());
    }

    public function test_trigram_scores_rank_closer_and_more_popular_titles_first(): void
    {
        $obscure = Title::factory()->create(['name' => 'Interstellar', 'popularity' => 0]);
        $popular = Title::factory()->create(['name' => 'Interstellar', 'popularity' => 200]);

        $scores = $this->service()->lexicalMatches('intersteller')->trigram;

        $this->assertSame([$popular->id, $obscure->id], $scores->keys()->all());
        $this->assertGreaterThan($scores[$obscure->id], $scores[$popular->id]);
    }

    public function test_hypothesis_scores_are_similarities_to_the_document_embedded_without_lowercasing(): void
    {
        $this->fakeHyde('Mad Max: Fury Road', queryVector: $this->vector([3 => 1.0]), documentVector: $this->vector([5 => 1.0]));
        $queryMatch = TitleSearchEmbedding::factory()->pointingAt(3)->create();
        $documentMatch = TitleSearchEmbedding::factory()->pointingAt(5)->create();

        $this->assertSame([$documentMatch->title_id, $queryMatch->title_id], $this->service()->hypothesisScores('Mad Max: Fury Road')->keys()->all());
        Http::assertSent(fn (Request $request) => ($request->data()['input'] ?? null) === ['Mad Max: Fury Road']);
    }

    public function test_hyde_ids_replace_semantic_search_with_the_hypothetical_document(): void
    {
        $this->fakeHyde(
            'Mad Max: Fury Road',
            queryVector: $this->vector([3 => 1.0]),
            documentVector: $this->vector([5 => 1.0, 4 => 0.6]),
        );
        TitleSearchEmbedding::factory()->pointingAt(3)->create();
        $nearDocument = TitleSearchEmbedding::factory()->pointingAt(4)->create();
        $documentMatch = TitleSearchEmbedding::factory()->pointingAt(5)->create();

        $this->assertSame([$documentMatch->title_id, $nearDocument->title_id], $this->service()->hydeIds('post apocalyptic war rig chase', 2)->all());
        Http::assertNotSent(fn (Request $request) => ($request->data()['input'] ?? null) === ['post apocalyptic war rig chase']);
    }

    public function test_hyde_ids_search_the_title_the_llm_guesses(): void
    {
        $this->fakeHyde(
            "Mad Max: Fury Road\nA desert chase.",
            queryVector: $this->vector([3 => 1.0]),
            documentVector: $this->vector([5 => 1.0]),
            title: 'Mad Max: Fury Road',
        );
        TitleSearchEmbedding::factory()->pointingAt(5)->create();
        $guessed = $this->createSearchableTitle(['name' => 'Mad Max: Fury Road']);
        Artisan::call('titles:refresh-searchable');

        $this->assertContains($guessed->id, $this->service()->hydeIds('post apocalyptic war rig chase'));
        $this->assertNotContains($guessed->id, $this->service()->hybridIds('post apocalyptic war rig chase'));
    }

    public function test_hyde_ids_match_the_title_guess_despite_spelling_differences(): void
    {
        $this->fakeHyde('Old Boy', queryVector: $this->vector([3 => 1.0]), documentVector: $this->vector([5 => 1.0]), title: 'Old Boy');
        TitleSearchEmbedding::factory()->pointingAt(5)->create();
        $oldboy = Title::factory()->create(['name' => 'Oldboy']);

        $this->assertContains($oldboy->id, $this->service()->hydeIds('man imprisoned fifteen years seeks revenge'));
        $this->assertNotContains($oldboy->id, $this->service()->hybridIds('man imprisoned fifteen years seeks revenge'));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function hydeGateCases(): array
    {
        return [
            'exact title' => ['the silence of the lambs', false],
            'one typo in a title' => ['lord of the rigns', false],
            'every term in a title' => ['dark knights rising', false],
            'several typos in a title' => ['the silense of the lamb', false],
            'keyword is not a title' => ['dark knight vigilante', true],
            'under three words' => ['haunted lighthouse', false],
            'language written without spaces' => ['戦争で孤児になった兄妹', true],
        ];
    }

    #[DataProvider('hydeGateCases')]
    public function test_hyde_ids_ask_the_llm_only_for_descriptive_queries(string $query, bool $asksLlm): void
    {
        $this->fakeHyde('A hypothetical film', queryVector: $this->vector([3 => 1.0]), documentVector: $this->vector([5 => 1.0]));
        $this->createSearchableTitle(['name' => 'The Silence of the Lambs']);
        $this->createSearchableTitle(['name' => 'The Lord of the Rings']);
        $this->createSearchableTitle(['name' => 'The Dark Knight Rises'])
            ->keywords()->attach(Keyword::factory()->create(['name' => 'vigilante']));
        Artisan::call('titles:refresh-searchable');

        $this->service()->hydeIds($query);

        $this->assertSame($asksLlm, Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'))->isNotEmpty());
    }

    public function test_hyde_ids_see_a_close_title_match_ranked_beyond_the_limit(): void
    {
        $this->fakeHyde('A hypothetical film', queryVector: $this->vector([3 => 1.0]), documentVector: $this->vector([5 => 1.0]));
        $this->createSearchableTitle(['name' => 'The Dark Knight Rises', 'popularity' => 0]);
        $keywordMatch = $this->createSearchableTitle(['name' => 'Unrelated Film', 'popularity' => 500]);
        $keywordMatch->keywords()->attach(Keyword::factory()->create(['name' => 'dark knight rising']));
        Artisan::call('titles:refresh-searchable');

        $this->assertSame([$keywordMatch->id], $this->service()->lexicalMatches('dark knights rising', 1)->fullText->keys()->all());

        $this->service()->hydeIds('dark knights rising', 1);

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'));
    }

    public function test_hyde_ids_fall_back_to_hybrid_search_when_no_llm_provider_is_available(): void
    {
        config(['services.llm.providers' => ['openai']]);
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response('server error', 500),
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [['index' => 0, 'embedding' => TitleSearchEmbeddingFactory::unitVector(3)]],
            ]),
        ]);
        $textMatch = $this->createSearchableTitle(['name' => 'Galaxy Quest']);
        $textMatch->keywords()->attach(Keyword::factory()->create(['name' => 'space crew']));
        $semanticMatch = TitleSearchEmbedding::factory()->pointingAt(3)->create();
        Artisan::call('titles:refresh-searchable');

        $this->assertSame([$textMatch->id, $semanticMatch->title_id], $this->service()->hydeIds('galaxy quest space crew')->all());
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'));
    }

    public function test_hyde_ids_fall_back_to_lexical_search_when_embeddings_fail(): void
    {
        config(['services.llm.providers' => ['openai']]);
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode(['title' => null, 'document' => 'A space crew comedy'])]]]]),
            'api.openai.com/v1/embeddings' => Http::response('server error', 500),
        ]);
        $textMatch = $this->createSearchableTitle(['name' => 'Galaxy Quest']);
        $textMatch->keywords()->attach(Keyword::factory()->create(['name' => 'space crew']));
        TitleSearchEmbedding::factory()->create();
        Artisan::call('titles:refresh-searchable');

        $this->assertSame([$textMatch->id], $this->service()->hydeIds('galaxy quest space crew')->all());
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'));
    }
}
