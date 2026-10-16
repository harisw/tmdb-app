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

    public function test_hybrid_ids_fall_back_to_full_text_when_openai_fails(): void
    {
        Http::fake(['api.openai.com/*' => Http::response('server error', 500)]);
        $textMatch = $this->createSearchableTitle(['name' => 'Galaxy Quest']);
        TitleSearchEmbedding::factory()->create();
        Artisan::call('titles:refresh-searchable');

        $this->assertSame([$textMatch->id], $this->service()->hybridIds('galaxy')->all());
    }
}
