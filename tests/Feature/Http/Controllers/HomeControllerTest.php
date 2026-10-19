<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\ProductionCompany;
use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use App\Models\TitleSearchEmbedding;
use App\Utils\Enum\FeatureFlag;
use Database\Factories\TitleSearchEmbeddingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Bus::fake();
    }

    public function test_shared_props_expose_the_title_action_map(): void
    {
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('enums.TitleAction.like.on', 'like')
            ->where('enums.TitleAction.like.off', 'unlike')
            ->where('enums.TitleAction.like.ratable', true)
            ->where('enums.TitleAction.watchlist.ratable', false)
            ->where('enums.TitleAction.watched.off', 'watched_removed')
        );
    }

    public function test_index_renders_home_with_paginated_titles(): void
    {
        Title::factory(35)->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('titles.data', 30)
                ->where('titles.meta.current_page', 1)
                ->where('titles.meta.per_page', 30)
                ->where('titles.meta.total', 35)
                ->whereNot('titles.links.next', null)
            );
    }

    public function test_all_genres_page_renders_genres_with_their_titles(): void
    {
        $genre = Genre::factory()->create();
        $titles = Title::factory(2)->create();
        $genre->titles()->attach($titles->pluck('id'));

        $this->get('/genre/all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AllGenres')
                ->has('allGenres', 1)
                ->has('allGenres.0.titles', 2)
            );
    }

    public function test_all_providers_page_renders_providers_with_their_titles(): void
    {
        $title = Title::factory()->create();
        $titleProvider = TitleProvider::factory()->create(['title_id' => $title->id]);
        Provider::whereKey($titleProvider->provider_id)->update(['count' => 1000]);

        $this->get('/provider/all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AllProviders')
                ->where('allProviders.0.titles.0.id', $title->id)
            );
    }

    public function test_titles_by_genre_returns_only_that_genres_titles(): void
    {
        $genre = Genre::factory()->create(['slug' => 'drama']);
        $other = Genre::factory()->create(['slug' => 'comedy']);

        Title::factory(2)->create()->each(fn ($m) => $m->genres()->attach($genre));
        Title::factory()->create()->each(fn ($m) => $m->genres()->attach($other));

        $this->get('/genre/drama')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleByGenre')
                ->has('genre')
                ->has('titles.data', 2)
            );
    }

    public function test_languages_page_lists_languages_with_titles_and_filters_by_lang_param(): void
    {
        $en = Language::factory()->create(['code' => 'en']);
        $fr = Language::factory()->create(['code' => 'fr']);
        Title::factory(2)->create(['language_id' => $en->id]);
        Title::factory()->create(['language_id' => $fr->id]);
        Language::factory(2)->create(); // no titles -> excluded from the list

        $this->get('/languages?lang=en')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleByLanguage')
                ->has('languages', 2)
                ->has('titles.data', 2)
            );
    }

    public function test_languages_page_returns_no_titles_without_a_lang_param(): void
    {
        $this->get('/languages')
            ->assertInertia(fn ($page) => $page
                ->component('TitleByLanguage')
                ->where('titles', [])
            );
    }

    public function test_tags_page_lists_keywords_and_filters_by_tag_param(): void
    {
        $keyword = Keyword::factory()->create(['slug' => 'space-opera', 'count' => 10]);
        $titles = Title::factory(3)->create();
        $keyword->titles()->attach($titles->pluck('id'));
        Title::factory()->create(); // no keyword

        $this->get('/tags?tag=space-opera')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AllKeywords')
                ->has('tags')
                ->has('titles.data', 3)
            );
    }

    public function test_tags_page_returns_no_titles_without_a_tag_param(): void
    {
        $this->get('/tags')
            ->assertInertia(fn ($page) => $page
                ->component('AllKeywords')
                ->where('titles', [])
            );
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
        Title::factory()->create(); // different language

        $this->get('/search-all?lang=fr')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleSearchAll')
                ->has('titles.data', 2)
            );
    }

    public function test_search_all_filters_by_genre(): void
    {
        $genre = Genre::factory()->create(['slug' => 'thriller']);
        $titles = Title::factory(2)->create();
        $titles->each(fn ($m) => $m->genres()->attach($genre));
        Title::factory()->create(); // no genre

        $this->get('/search-all?gen=thriller')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleSearchAll')
                ->has('titles.data', 2)
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

    public function test_search_requires_a_query(): void
    {
        $this->getJson('/search')->assertUnprocessable()->assertJsonValidationErrors('q');
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
            ? Http::response(['choices' => [['message' => ['content' => 'Galaxy Quest: Never give up.']]]])
            : Http::response(['data' => [['index' => 0, 'embedding' => TitleSearchEmbeddingFactory::unitVector(3)]]]));
        $textMatch = $this->createSearchableTitle(['name' => 'Galaxy Quest Space Crew']);
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
        Http::assertSent(fn (Request $request) => ($request->data()['input'] ?? null) === ['Galaxy Quest: Never give up.']);
    }

    public function test_search_all_is_rate_limited(): void
    {
        foreach (range(1, 40) as $attempt) {
            $this->get('/search-all')->assertOk();
        }

        $this->get('/search-all')->assertTooManyRequests();
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
