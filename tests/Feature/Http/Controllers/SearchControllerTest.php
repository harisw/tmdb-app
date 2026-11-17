<?php

namespace Tests\Feature\Http\Controllers;

use App\Domain\Enums\TitleType;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\ProductionCompany;
use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Models\User;
use App\Utils\Enum\FeatureFlag;
use Database\Factories\TitleSearchEmbeddingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    public function test_search_requires_a_query(): void
    {
        $this->getJson('/search')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_search_rejects_a_query_over_two_hundred_characters(): void
    {
        $this->getJson('/search?q='.str_repeat('a', 201))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_search_all_rejects_a_query_over_two_hundred_characters(): void
    {
        $this->get('/search-all?q='.str_repeat('a', 201))
            ->assertSessionHasErrors('q');
    }

    public function test_search_all_rejects_an_array_query(): void
    {
        $this->get('/search-all?q[]=alien')
            ->assertSessionHasErrors('q');
    }

    public function test_search_all_rejects_array_filters(): void
    {
        $this->get('/search-all?lang[]=en&gen[]=drama&tag[]=space')
            ->assertSessionHasErrors(['lang', 'gen', 'tag']);
    }

    public function test_search_all_is_rate_limited(): void
    {
        foreach (range(1, 40) as $attempt) {
            $this->get('/search-all')->assertOk();
        }

        $this->get('/search-all')->assertTooManyRequests();
    }

    public function test_search_returns_matching_results(): void
    {
        $this->createSearchableTitle(['name' => 'Inception Dreams']);
        $this->createSearchableTitle(['name' => 'Unrelated Film']);
        $this->refreshSearchIndex();

        $this->get('/search?q=inception')
            ->assertOk()
            ->assertJsonPath('hasMore', false)
            ->assertJsonCount(1, 'results');
    }

    public function test_search_orders_results_by_relevance_rank(): void
    {
        $genreMatch = $this->createSearchableTitle(['name' => 'Unrelated Film', 'popularity' => 10]);
        $genreMatch->genres()->attach(Genre::factory()->create(['name' => 'Galaxy']));
        $nameMatch = $this->createSearchableTitle(['name' => 'Galaxy Quest', 'popularity' => 10]);
        $this->refreshSearchIndex();

        $this->get('/search?q=galaxy')
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.id', $nameMatch->id)
            ->assertJsonPath('results.1.id', $genreMatch->id);
    }

    public function test_search_sets_has_more_when_results_exceed_limit(): void
    {
        $genre = Genre::factory()->create(['name' => 'Space Adventure']);
        $company = ProductionCompany::factory()->create();

        Title::factory(11)->create()->each(function ($title) use ($genre, $company) {
            $title->genres()->attach($genre);
            $title->companies()->attach($company);
        });

        $this->refreshSearchIndex();

        $this->get('/search?q=space')
            ->assertOk()
            ->assertJsonPath('hasMore', true)
            ->assertJsonCount(10, 'results');
    }

    public function test_search_applies_the_title_type_filter_before_the_result_limit(): void
    {
        $movie = $this->createSearchableTitle(['name' => 'Galaxy Quest', 'type' => TitleType::MOVIE->value, 'popularity' => 0]);
        foreach (range(1, 11) as $show) {
            $this->createSearchableTitle(['name' => 'Galaxy Quest', 'type' => TitleType::TV->value, 'popularity' => 500]);
        }
        $this->refreshSearchIndex();

        $this->getJson('/search?q=galaxy&title_type='.TitleType::MOVIE->value)
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $movie->id);
    }

    public function test_search_hides_avoided_keywords_before_the_result_limit(): void
    {
        $gore = Keyword::factory()->create();
        $kept = $this->createSearchableTitle(['name' => 'Galaxy Quest', 'popularity' => 0]);
        foreach (range(1, 11) as $avoided) {
            $this->createSearchableTitle(['name' => 'Galaxy Quest', 'popularity' => 500])->keywords()->attach($gore);
        }
        $user = User::factory()->create();
        $user->avoidedKeywords()->attach($gore);
        $this->refreshSearchIndex();

        $this->actingAs($user)
            ->getJson('/search?q=galaxy')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $kept->id);
    }

    public function test_search_all_renders_page_without_params(): void
    {
        Genre::factory(2)->create();
        Language::factory(2)->create();

        $this->get('/search-all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleSearchAll')
                ->has('genres')
                ->has('languages')
                ->has('tags')
                ->where('titles', [])
            );
    }

    public function test_search_all_filters_by_language(): void
    {
        $language = Language::factory()->create(['code' => 'fr']);
        Title::factory(2)->create(['language_id' => $language->id]);
        $english = Language::factory()->create(['code' => 'en']);
        Title::factory()->create(['language_id' => $english->id]);

        $this->get('/search-all?lang=fr')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleSearchAll')
                ->has('titles.data', 2)
            );
    }

    public function test_search_all_filters_by_genre_without_a_query(): void
    {
        $drama = Genre::factory()->create(['slug' => 'drama']);
        $inDrama = Title::factory()->create();
        $drama->titles()->attach($inDrama);
        Title::factory()->create();

        $this->get('/search-all?gen=drama')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleSearchAll')
                ->has('titles.data', 1)
                ->where('titles.data.0.id', $inDrama->id)
            );
    }

    public function test_search_all_filters_by_tag(): void
    {
        $keyword = Keyword::factory()->create(['slug' => 'heist']);
        $titles = Title::factory(2)->create();
        $titles->each(fn ($m) => $m->keywords()->attach($keyword));
        Title::factory()->create(); // no keyword

        $this->get('/search-all?tag=heist')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleSearchAll')
                ->has('titles.data', 2)
            );
    }

    public function test_search_all_with_text_query_returns_matching_titles(): void
    {
        $match = $this->createSearchableTitle(['name' => 'Galaxy Quest']);
        $this->createSearchableTitle(['name' => 'Earth Bound']);
        $this->refreshSearchIndex();

        $this->get('/search-all?q=galaxy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleSearchAll')
                ->has('titles.data', 1)
                ->where('titles.data.0.id', $match->id)
            );
    }

    public function test_search_all_with_unmatched_text_query_returns_no_titles(): void
    {
        $this->createSearchableTitle(['name' => 'Galaxy Quest']);
        $this->refreshSearchIndex();

        $this->get('/search-all?q=nonexistentword')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('titles.data', 0));
    }

    public function test_search_all_orders_text_results_by_relevance(): void
    {
        $nameMatch = $this->createSearchableTitle(['name' => 'Galaxy Quest', 'popularity' => 10]);
        $genreMatch = $this->createSearchableTitle(['name' => 'Unrelated Film', 'popularity' => 10]);
        $genreMatch->genres()->attach(Genre::factory()->create(['name' => 'Galaxy']));
        $nameMatch->update(['created_at' => now()->subYear()]);
        $this->refreshSearchIndex();

        $this->get('/search-all?q=galaxy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('titles.data.0.id', $nameMatch->id)
                ->where('titles.data.1.id', $genreMatch->id)
            );
    }

    public function test_search_all_keeps_relevance_order_when_filtering_by_genre(): void
    {
        $sciFi = Genre::factory()->create(['slug' => 'science-fiction']);
        $nameMatch = $this->createSearchableTitle(['name' => 'Galaxy Quest', 'popularity' => 10]);
        $keywordMatch = $this->createSearchableTitle(['name' => 'Unrelated Film', 'popularity' => 10]);
        $keywordMatch->keywords()->attach(Keyword::factory()->create(['name' => 'galaxy']));
        $this->createSearchableTitle(['name' => 'Galaxy Express']);
        $nameMatch->genres()->attach($sciFi);
        $keywordMatch->genres()->attach($sciFi);
        $nameMatch->update(['created_at' => now()->subYear()]);
        $this->refreshSearchIndex();

        $this->get('/search-all?q=galaxy&gen=science-fiction')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('titles.data', 2)
                ->where('titles.data.0.id', $nameMatch->id)
                ->where('titles.data.1.id', $keywordMatch->id)
            );
    }

    public function test_search_all_blends_semantic_results(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'data' => [['index' => 0, 'embedding' => TitleSearchEmbeddingFactory::unitVector(3)]],
        ])]);
        $textMatch = $this->createSearchableTitle(['name' => 'Galaxy Quest']);
        $semanticMatch = TitleSearchEmbedding::factory()->pointingAt(3)->create();
        $this->refreshSearchIndex();

        $this->get('/search-all?q=galaxy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('titles.data', 2)
                ->where('titles.data.0.id', $textMatch->id)
                ->where('titles.data.1.id', $semanticMatch->title_id)
            );

        Http::assertSentCount(1);
    }

    public function test_search_all_skips_hyde_when_hyde_search_is_inactive(): void
    {
        Http::fake(['api.openai.com/v1/embeddings' => Http::response([
            'data' => [['index' => 0, 'embedding' => TitleSearchEmbeddingFactory::unitVector(3)]],
        ])]);
        $this->createSearchableTitle(['name' => 'Galaxy Quest']);
        $this->refreshSearchIndex();

        $this->get('/search-all?q=galaxy')->assertOk();

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'));
    }

    public function test_search_all_adds_hyde_results_when_hyde_search_is_active(): void
    {
        Feature::for('global')->activate(FeatureFlag::HydeSearch);
        config(['services.llm.providers' => ['openai']]);
        Http::fake(fn (Request $request) => str_ends_with($request->url(), '/chat/completions')
            ? Http::response(['choices' => [['message' => ['content' => json_encode(['title' => null, 'document' => 'Galaxy Quest'])]]]])
            : Http::response(['data' => [['index' => 0, 'embedding' => TitleSearchEmbeddingFactory::unitVector(3)]]]));
        $textMatch = $this->createSearchableTitle(['name' => 'Galaxy Quest']);
        $textMatch->keywords()->attach(Keyword::factory()->create(['name' => 'space crew']));
        $semanticMatch = TitleSearchEmbedding::factory()->pointingAt(3)->create();
        $this->refreshSearchIndex();

        $this->get('/search-all?q=galaxy quest space crew')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('titles.data', 2)
                ->where('titles.data.0.id', $textMatch->id)
                ->where('titles.data.1.id', $semanticMatch->title_id)
            );

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'));
        Http::assertSent(fn (Request $request) => ($request->data()['input'] ?? null) === ['Galaxy Quest']);
    }

    // Helpers

    private function createSearchableTitle(array $overrides = []): Title
    {
        $title = Title::factory()->create($overrides);
        $title->genres()->attach(Genre::factory()->create());
        $title->companies()->attach(ProductionCompany::factory()->create());

        return $title;
    }

    private function refreshSearchIndex(): void
    {
        Artisan::call('titles:refresh-searchable');
    }
}
