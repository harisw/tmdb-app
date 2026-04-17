<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\ProductionCompany;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_index_returns_ok(): void
    {
        Title::factory(3)->create();

        $this->get('/')->assertOk();
    }

    public function test_index_renders_home_component_with_props(): void
    {
        Title::factory(2)->create();

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('titles')
                ->has('poster_url')
                ->has('backdrop_url')
            );
    }

    public function test_index_paginates_titles(): void
    {
        Title::factory(35)->create();

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('titles.data', 30)
            );
    }

    public function test_get_all_genres_renders_genres_page(): void
    {
        Genre::factory(3)->create();

        $this->get('/genre/all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AllGenres')
                ->has('allGenres', 3)
                ->has('poster_url')
                ->has('backdrop_url')
            );
    }

    public function test_get_all_genres_eager_loads_titles(): void
    {
        $genre = Genre::factory()->create();
        $titles = Title::factory(2)->create();
        $genre->titles()->attach($titles->pluck('id'));

        $this->get('/genre/all')
            ->assertInertia(fn ($page) => $page
                ->component('AllGenres')
                ->has('allGenres.0.titles', 2)
            );
    }

    public function test_get_by_genre_renders_title_by_genre_component(): void
    {
        $genre = Genre::factory()->create(['slug' => 'action']);
        Title::factory(2)->create()->each(fn ($title) => $title->genres()->attach($genre));

        $this->get('/genre/action')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleByGenre')
                ->has('genre')
                ->has('titles')
                ->has('poster_url')
                ->has('backdrop_url')
            );
    }

    public function test_get_by_genre_returns_only_titles_for_that_genre(): void
    {
        $genre = Genre::factory()->create(['slug' => 'drama']);
        $other = Genre::factory()->create(['slug' => 'comedy']);

        $matched = Title::factory(2)->create()->each(fn ($m) => $m->genres()->attach($genre));
        Title::factory()->create()->each(fn ($m) => $m->genres()->attach($other));

        $this->get('/genre/drama')
            ->assertInertia(fn ($page) => $page
                ->component('TitleByGenre')
                ->has('titles.data', 2)
            );
    }

    public function test_get_by_language_renders_language_page(): void
    {
        Language::factory(3)->create();

        $this->get('/languages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleByLanguage')
                ->has('languages', 3)
                ->has('poster_url')
                ->has('backdrop_url')
            );
    }

    public function test_get_by_language_without_lang_param_returns_null_titles(): void
    {
        $this->get('/languages')
            ->assertInertia(fn ($page) => $page
                ->component('TitleByLanguage')
                ->where('titles', null)
            );
    }

    public function test_get_by_language_with_lang_param_returns_titles(): void
    {
        $language = Language::factory()->create(['code' => 'en']);
        Title::factory(2)->create(['language_id' => $language->id]);
        Title::factory()->create(); // different language

        $this->get('/languages?lang=en')
            ->assertInertia(fn ($page) => $page
                ->component('TitleByLanguage')
                ->has('titles.data', 2)
            );
    }

    public function test_get_by_tag_renders_keyword_page(): void
    {
        Keyword::factory(5)->create(['count' => 10]);

        $this->get('/tags')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleByKeyword')
                ->has('tags')
                ->has('poster_url')
                ->has('backdrop_url')
            );
    }

    public function test_get_by_tag_without_tag_param_returns_null_titles(): void
    {
        $this->get('/tags')
            ->assertInertia(fn ($page) => $page
                ->component('TitleByKeyword')
                ->where('titles', null)
            );
    }

    public function test_get_by_tag_with_tag_param_returns_titles(): void
    {
        $keyword = Keyword::factory()->create(['slug' => 'space-opera']);
        $titles = Title::factory(3)->create();
        $keyword->titles()->attach($titles->pluck('id'));
        Title::factory()->create(); // no keyword

        $this->get('/tags?tag=space-opera')
            ->assertInertia(fn ($page) => $page
                ->component('TitleByKeyword')
                ->has('titles.data', 3)
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
                ->has('poster_url')
                ->has('backdrop_url')
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
