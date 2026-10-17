<?php

namespace Tests\Feature\Service\Search;

use App\Models\Genre;
use App\Models\ProductionCompany;
use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\Search\HybridSearchService;
use Database\Factories\TitleSearchEmbeddingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
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

    private function createSearchableTitle(array $overrides = []): Title
    {
        $title = Title::factory()->create($overrides);
        $title->genres()->attach(Genre::factory()->create());
        $title->companies()->attach(ProductionCompany::factory()->create());

        return $title;
    }

    public function test_fuse_ranks_ids_found_by_both_lists_first(): void
    {
        $fused = $this->service()->fuse(collect([1, 2, 3]), collect([1, 3, 4]));

        $this->assertSame([1, 3, 2, 4], $fused->all());
    }

    public function test_fuse_keeps_single_list_order(): void
    {
        $this->assertSame([5, 9, 7], $this->service()->fuse(collect([5, 9, 7]))->all());
    }

    public function test_full_text_ids_rank_popular_titles_above_equal_text_matches(): void
    {
        $unknown = $this->createSearchableTitle(['name' => 'The Dark Knight', 'popularity' => 0]);
        $popular = $this->createSearchableTitle(['name' => 'The Dark Knight', 'popularity' => 150]);
        $obscure = $this->createSearchableTitle(['name' => 'The Dark Knight', 'popularity' => 2]);
        Artisan::call('titles:refresh-searchable');

        $this->assertSame([$popular->id, $obscure->id, $unknown->id], $this->service()->fullTextIds('the dark knight')->all());
    }

    public function test_semantic_ids_are_ordered_by_cosine_distance(): void
    {
        $this->fakeQueryEmbedding(3);
        $far = TitleSearchEmbedding::factory()->pointingAt(7)->create();
        $near = TitleSearchEmbedding::factory()->pointingAt(3)->create();

        $this->assertSame([$near->title_id, $far->title_id], $this->service()->semanticIds('lonely robot')->all());
    }

    public function test_semantic_ids_respect_the_adult_content_scope(): void
    {
        $this->fakeQueryEmbedding(3);
        TitleSearchEmbedding::factory()->pointingAt(3)->for(Title::factory()->create(['is_adult' => true]))->create();
        $safe = TitleSearchEmbedding::factory()->pointingAt(4)->create();

        $this->assertSame([$safe->title_id], $this->service()->semanticIds('anything')->all());
    }

    public function test_query_embeddings_are_cached_per_normalized_query(): void
    {
        $this->fakeQueryEmbedding(3);
        TitleSearchEmbedding::factory()->create();

        $this->service()->semanticIds('Lonely Robot');
        $this->service()->semanticIds('  lonely robot ');

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

    public function test_trigram_ids_match_misspelled_titles(): void
    {
        $match = Title::factory()->create(['name' => 'The Shawshank Redemption']);
        Title::factory()->create(['name' => 'Galaxy Quest']);

        $this->assertSame([$match->id], $this->service()->trigramIds('shawshenk redemption')->all());
    }

    public function test_trigram_scores_rank_closer_and_more_popular_titles_first(): void
    {
        $obscure = Title::factory()->create(['name' => 'Interstellar', 'popularity' => 0]);
        $popular = Title::factory()->create(['name' => 'Interstellar', 'popularity' => 200]);

        $scores = $this->service()->trigramScores('intersteller');

        $this->assertSame([$popular->id, $obscure->id], $scores->keys()->all());
        $this->assertGreaterThan($scores[$obscure->id], $scores[$popular->id]);
    }

    public function test_trigram_ids_respect_the_adult_content_scope(): void
    {
        Title::factory()->create(['name' => 'Interstellar', 'is_adult' => true]);

        $this->assertEmpty($this->service()->trigramIds('intersteller'));
    }
}
