<?php

namespace Tests\Feature\Http\Controllers;

use App\Domain\Enums\TitleType;
use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class GenreControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Bus::fake();
    }

    private function genreWithTitles(int $count, array $genre = [], array $title = []): Genre
    {
        $model = Genre::factory()->create(['count' => $count, ...$genre]);
        $model->titles()->attach(Title::factory($count)->create($title)->pluck('id'));

        return $model;
    }

    public function test_index_fills_every_genre_row_on_its_own(): void
    {
        $drama = $this->genreWithTitles(20);
        $comedy = $this->genreWithTitles(18);

        $this->get('/genre/all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AllGenres')
                ->has('allGenres', 2)
                ->where('allGenres.0.id', $drama->id)
                ->has('allGenres.0.titles', 15)
                ->where('allGenres.1.id', $comedy->id)
                ->has('allGenres.1.titles', 15)
            );
    }

    public function test_index_skips_genres_without_titles(): void
    {
        $this->genreWithTitles(2);
        Genre::factory()->create(['count' => 0]);
        Genre::factory()->create(['count' => 5000]);

        $this->get('/genre/all')->assertInertia(fn ($page) => $page->has('allGenres', 1));
    }

    public function test_index_orders_genres_by_their_stored_count(): void
    {
        $smaller = $this->genreWithTitles(3, ['count' => 120]);
        $larger = $this->genreWithTitles(2, ['count' => 4400]);

        $this->get('/genre/all')->assertInertia(fn ($page) => $page
            ->where('allGenres.0.id', $larger->id)
            ->where('allGenres.0.count', 4400)
            ->where('allGenres.1.id', $smaller->id)
            ->where('allGenres.1.count', 120)
        );
    }

    public function test_index_rows_show_the_most_popular_titles_first(): void
    {
        $genre = Genre::factory()->create(['count' => 2]);
        $quiet = Title::factory()->create(['popularity' => 1]);
        $loud = Title::factory()->create(['popularity' => 99]);
        $genre->titles()->attach([$quiet->id, $loud->id]);

        $this->get('/genre/all')->assertInertia(fn ($page) => $page
            ->where('allGenres.0.titles.0.id', $loud->id)
            ->where('allGenres.0.titles.1.id', $quiet->id)
        );
    }

    public function test_index_rows_honour_the_title_type_toggle(): void
    {
        $genre = Genre::factory()->create(['count' => 2]);
        $movie = Title::factory()->create(['type' => TitleType::MOVIE]);
        $show = Title::factory()->create(['type' => TitleType::TV]);
        $genre->titles()->attach([$movie->id, $show->id]);

        $this->get('/genre/all?title_type='.TitleType::TV->value)->assertInertia(fn ($page) => $page
            ->has('allGenres.0.titles', 1)
            ->where('allGenres.0.titles.0.id', $show->id)
        );
    }

    public function test_index_lists_a_title_in_every_genre_it_belongs_to(): void
    {
        $drama = Genre::factory()->create(['count' => 2]);
        $comedy = Genre::factory()->create(['count' => 1]);
        $shared = Title::factory()->create();
        $drama->titles()->attach([$shared->id, Title::factory()->create()->id]);
        $comedy->titles()->attach($shared->id);

        $this->get('/genre/all')->assertInertia(fn ($page) => $page
            ->has('allGenres.0.titles', 2)
            ->has('allGenres.1.titles', 1)
            ->where('allGenres.1.titles.0.id', $shared->id)
        );
    }

    public function test_rows_and_grid_leave_keywords_for_the_modal_to_fetch(): void
    {
        $genre = $this->genreWithTitles(1, ['slug' => 'drama']);
        $genre->titles->first()->keywords()->attach(Keyword::factory()->create());

        $this->get('/genre/all')->assertInertia(fn ($page) => $page
            ->missing('allGenres.0.titles.0.keywords')
            ->has('allGenres.0.titles.0.genres', 1)
        );

        $this->get('/genre/drama')->assertInertia(fn ($page) => $page
            ->missing('titles.data.0.keywords')
            ->has('titles.data.0.genres', 1)
        );
    }

    public function test_show_returns_only_that_genres_titles(): void
    {
        $drama = $this->genreWithTitles(2, ['slug' => 'drama']);
        $this->genreWithTitles(1, ['slug' => 'comedy']);

        $this->get('/genre/drama')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleByGenre')
                ->where('genre.id', $drama->id)
                ->has('titles.data', 2)
                ->missing('titles.meta.total')
                ->where('titles.links.next', null)
            );
    }

    public function test_show_sorts_by_popularity_by_default(): void
    {
        $genre = Genre::factory()->create(['slug' => 'drama']);
        $quiet = Title::factory()->create(['popularity' => 1]);
        $loud = Title::factory()->create(['popularity' => 99]);
        $genre->titles()->attach([$quiet->id, $loud->id]);

        $this->get('/genre/drama')->assertInertia(fn ($page) => $page
            ->where('sort', 'popular')
            ->has('sorts', 3)
            ->where('titles.data.0.id', $loud->id)
        );
    }

    public function test_show_sorts_by_newest_release_leaving_out_undated_titles(): void
    {
        $genre = Genre::factory()->create(['slug' => 'drama']);
        $undated = Title::factory()->create(['release_date' => null]);
        $older = Title::factory()->create(['release_date' => '1999-01-01']);
        $newer = Title::factory()->create(['release_date' => '2024-01-01']);
        $genre->titles()->attach([$undated->id, $older->id, $newer->id]);

        $this->get('/genre/drama?sort=newest')->assertInertia(fn ($page) => $page
            ->where('sort', 'newest')
            ->has('titles.data', 2)
            ->where('titles.data.0.id', $newer->id)
            ->where('titles.data.1.id', $older->id)
        );
    }

    public function test_show_cursor_pages_through_tied_release_dates_without_skips_or_repeats(): void
    {
        $genre = Genre::factory()->create(['slug' => 'drama']);
        $titles = Title::factory(35)->create(['release_date' => '2020-01-01']);
        $genre->titles()->attach($titles->pluck('id'));

        $first = $this->get('/genre/drama?sort=newest')->inertiaProps('titles');
        $second = $this->get($first['links']['next'].'&sort=newest')->inertiaProps('titles');

        $this->assertCount(30, $first['data']);
        $this->assertCount(5, $second['data']);
        $this->assertNull($second['links']['next']);
        $this->assertEqualsCanonicalizing(
            $titles->pluck('id')->all(),
            collect([...$first['data'], ...$second['data']])->pluck('id')->all()
        );
    }

    public function test_show_sorts_by_recently_added(): void
    {
        $genre = Genre::factory()->create(['slug' => 'drama']);
        $earlier = Title::factory()->create(['created_at' => Carbon::parse('2026-01-01')]);
        $later = Title::factory()->create(['created_at' => Carbon::parse('2026-06-01')]);
        $genre->titles()->attach([$earlier->id, $later->id]);

        $this->get('/genre/drama?sort=added')->assertInertia(fn ($page) => $page
            ->where('titles.data.0.id', $later->id)
        );
    }

    public function test_show_rejects_an_unknown_sort(): void
    {
        Genre::factory()->create(['slug' => 'drama']);

        $this->get('/genre/drama?sort=bogus')->assertSessionHasErrors('sort');
    }
}
