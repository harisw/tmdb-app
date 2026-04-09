<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Language;
use App\Models\Movie;
use App\Models\ProductionCompany;
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
        Movie::factory(3)->create();

        $this->get('/')->assertOk();
    }

    public function test_index_renders_home_component_with_props(): void
    {
        Movie::factory(2)->create();

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('movies')
                ->has('poster_url')
                ->has('backdrop_url')
            );
    }

    public function test_index_paginates_movies(): void
    {
        Movie::factory(35)->create();

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('movies.data', 30)
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

    public function test_get_all_genres_eager_loads_movies(): void
    {
        $genre = Genre::factory()->create();
        $movies = Movie::factory(2)->create();
        $genre->movies()->attach($movies->pluck('id'));

        $this->get('/genre/all')
            ->assertInertia(fn ($page) => $page
                ->component('AllGenres')
                ->has('allGenres.0.movies', 2)
            );
    }

    public function test_get_by_genre_renders_movie_by_genre_component(): void
    {
        $genre = Genre::factory()->create(['slug' => 'action']);
        Movie::factory(2)->create()->each(fn ($movie) => $movie->genres()->attach($genre));

        $this->get('/genre/action')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MovieByGenre')
                ->has('genre')
                ->has('movies')
                ->has('poster_url')
                ->has('backdrop_url')
            );
    }

    public function test_get_by_genre_returns_only_movies_for_that_genre(): void
    {
        $genre = Genre::factory()->create(['slug' => 'drama']);
        $other = Genre::factory()->create(['slug' => 'comedy']);

        $matched = Movie::factory(2)->create()->each(fn ($m) => $m->genres()->attach($genre));
        Movie::factory()->create()->each(fn ($m) => $m->genres()->attach($other));

        $this->get('/genre/drama')
            ->assertInertia(fn ($page) => $page
                ->component('MovieByGenre')
                ->has('movies.data', 2)
            );
    }

    public function test_get_by_language_renders_language_page(): void
    {
        Language::factory(3)->create();

        $this->get('/languages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MovieByLanguage')
                ->has('languages', 3)
                ->has('poster_url')
                ->has('backdrop_url')
            );
    }

    public function test_get_by_language_without_lang_param_returns_null_movies(): void
    {
        $this->get('/languages')
            ->assertInertia(fn ($page) => $page
                ->component('MovieByLanguage')
                ->where('movies', null)
            );
    }

    public function test_get_by_language_with_lang_param_returns_movies(): void
    {
        $language = Language::factory()->create(['code' => 'en']);
        Movie::factory(2)->create(['language_id' => $language->id]);
        Movie::factory()->create(); // different language

        $this->get('/languages?lang=en')
            ->assertInertia(fn ($page) => $page
                ->component('MovieByLanguage')
                ->has('movies.data', 2)
            );
    }

    public function test_get_by_tag_renders_keyword_page(): void
    {
        Keyword::factory(5)->create(['count' => 10]);

        $this->get('/tags')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MovieByKeyword')
                ->has('tags')
                ->has('poster_url')
                ->has('backdrop_url')
            );
    }

    public function test_get_by_tag_without_tag_param_returns_null_movies(): void
    {
        $this->get('/tags')
            ->assertInertia(fn ($page) => $page
                ->component('MovieByKeyword')
                ->where('movies', null)
            );
    }

    public function test_get_by_tag_with_tag_param_returns_movies(): void
    {
        $keyword = Keyword::factory()->create(['slug' => 'space-opera']);
        $movies = Movie::factory(3)->create();
        $keyword->movies()->attach($movies->pluck('id'));
        Movie::factory()->create(); // no keyword

        $this->get('/tags?tag=space-opera')
            ->assertInertia(fn ($page) => $page
                ->component('MovieByKeyword')
                ->has('movies.data', 3)
            );
    }

    public function test_search_returns_matching_results(): void
    {
        $this->createSearchableMovie(['title' => 'Inception Dreams']);
        $this->createSearchableMovie(['title' => 'Unrelated Film']);
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

        Movie::factory(11)->create()->each(function ($movie) use ($genre, $company) {
            $movie->genres()->attach($genre);
            $movie->companies()->attach($company);
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
                ->component('MovieSearchAll')
                ->has('genres')
                ->has('languages')
                ->has('tags')
                ->has('poster_url')
                ->has('backdrop_url')
                ->where('movies', [])
            );
    }

    public function test_search_all_filters_by_language(): void
    {
        $language = Language::factory()->create(['code' => 'fr']);
        Movie::factory(2)->create(['language_id' => $language->id]);
        Movie::factory()->create(); // different language

        $this->get('/search-all?lang=fr')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MovieSearchAll')
                ->has('movies.data', 2)
            );
    }

    public function test_search_all_filters_by_genre(): void
    {
        $genre = Genre::factory()->create(['slug' => 'thriller']);
        $movies = Movie::factory(2)->create();
        $movies->each(fn ($m) => $m->genres()->attach($genre));
        Movie::factory()->create(); // no genre

        $this->get('/search-all?gen=thriller')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MovieSearchAll')
                ->has('movies.data', 2)
            );
    }

    public function test_search_all_filters_by_tag(): void
    {
        $keyword = Keyword::factory()->create(['slug' => 'heist']);
        $movies = Movie::factory(2)->create();
        $movies->each(fn ($m) => $m->keywords()->attach($keyword));
        Movie::factory()->create(); // no keyword

        $this->get('/search-all?tag=heist')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MovieSearchAll')
                ->has('movies.data', 2)
            );
    }

    public function test_search_all_with_text_query_returns_matching_movies(): void
    {
        $match = $this->createSearchableMovie(['title' => 'Galaxy Quest']);
        $this->createSearchableMovie(['title' => 'Earth Bound']);
        $this->refreshSearchIndex();

        $this->get('/search-all?q=galaxy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MovieSearchAll')
                ->has('movies.data', 1)
                ->where('movies.data.0.id', $match->id)
            );
    }

    // Helpers

    private function createSearchableMovie(array $overrides = []): Movie
    {
        $movie = Movie::factory()->create($overrides);
        $movie->genres()->attach(Genre::factory()->create());
        $movie->companies()->attach(ProductionCompany::factory()->create());

        return $movie;
    }

    private function refreshSearchIndex(): void
    {
        Artisan::call('movies:refresh-searchable');
    }
}
