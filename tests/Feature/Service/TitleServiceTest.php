<?php

namespace Tests\Feature\Service;

use App\Models\Genre;
use App\Models\Language;
use App\Models\Title;
use App\Services\TitleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TitleServiceTest extends TestCase
{
    use RefreshDatabase;

    private Language $language;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->language = Language::factory()->create();

        $this->headers = [
            'id', 'name', 'type', 'rating', 'status', 'release_date', 'revenue',
            'runtime', 'backdrop_path', 'imdb_id', 'overview', 'popularity',
            'poster_path', 'tagline', 'language_id', 'homepage',
            'genres', 'keywords', 'production_companies',
        ];
    }

    private function makeRow(array $overrides = []): array
    {
        return array_merge([
            'id' => '12345',
            'name' => 'The Matrix',
            'type' => 'movie',
            'rating' => '8.7',
            'status' => 'Released',
            'release_date' => '1999-03-31',
            'revenue' => '463517383',
            'runtime' => '136',
            'backdrop_path' => '/backdrop.jpg',
            'imdb_id' => 'tt0133093',
            'overview' => 'A computer hacker learns the truth.',
            'popularity' => '85.4',
            'poster_path' => '/poster.jpg',
            'tagline' => 'Free your mind.',
            'language_id' => (string) $this->language->id,
            'homepage' => 'https://matrix.example.com',
            'genres' => 'Action, Science Fiction',
            'keywords' => 'hacker, virtual reality',
            'production_companies' => 'Warner Bros, Village Roadshow',
        ], $overrides);
    }

    public function test_insert_bulk_creates_title_in_database(): void
    {
        $rows = [$this->makeRow()];
        $data = array_map(fn ($r) => array_values($r), $rows);

        $count = TitleService::insertBulk($data, $this->headers);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('titles', [
            'name' => 'The Matrix',
            'slug' => 'the-matrix',
            'tmdb_id' => 12345,
        ]);
    }

    public function test_insert_bulk_skips_adult_titles(): void
    {
        $rows = [
            array_values([...$this->makeRow(), 'is_adult' => true]),
            array_values([...$this->makeRow(['id' => '2', 'name' => 'Safe Title']), 'is_adult' => false]),
        ];

        $count = TitleService::insertBulk($rows, [...$this->headers, 'is_adult']);

        $this->assertSame(1, $count);
        $this->assertSame(['safe-title'], Title::pluck('slug')->all());
    }

    public function test_insert_bulk_attaches_genres_to_title(): void
    {
        $data = [array_values($this->makeRow(['genres' => 'Action, Science Fiction']))];

        TitleService::insertBulk($data, $this->headers);

        $title = Title::where('slug', 'the-matrix')->first();
        $this->assertCount(2, $title->genres);
        $this->assertDatabaseHas('genres', ['slug' => 'action', 'name' => 'Action']);
        $this->assertDatabaseHas('genres', ['slug' => 'science-fiction', 'name' => 'Science Fiction']);
    }

    public function test_insert_bulk_attaches_keywords_to_title(): void
    {
        $data = [array_values($this->makeRow(['keywords' => 'hacker, virtual reality']))];

        TitleService::insertBulk($data, $this->headers);

        $title = Title::where('slug', 'the-matrix')->first();
        $this->assertCount(2, $title->keywords);
        $this->assertDatabaseHas('keywords', ['slug' => 'hacker']);
        $this->assertDatabaseHas('keywords', ['slug' => 'virtual-reality']);
    }

    public function test_insert_bulk_attaches_production_companies_to_title(): void
    {
        $data = [array_values($this->makeRow(['production_companies' => 'Warner Bros']))];

        TitleService::insertBulk($data, $this->headers);

        $title = Title::where('slug', 'the-matrix')->first();
        $this->assertCount(1, $title->companies);
        $this->assertDatabaseHas('production_companies', ['slug' => 'warner-bros']);
    }

    public function test_insert_bulk_skips_rows_repeating_a_tmdb_id(): void
    {
        $rows = [
            $this->makeRow(['id' => '1', 'name' => 'The Matrix']),
            $this->makeRow(['id' => '1', 'name' => 'The Matrix Renamed']),
        ];
        $data = array_map(fn ($r) => array_values($r), $rows);

        $count = TitleService::insertBulk($data, $this->headers);

        $this->assertSame(1, $count);
        $this->assertSame(1, Title::count());
    }

    public function test_insert_matches_a_title_inserted_since_the_cache_loaded_by_tmdb_id(): void
    {
        $service = new TitleService;
        $existing = Title::factory()->create([
            'slug' => 'old-name',
            'tmdb_id' => 12345,
            'type' => 'movie',
            'language_id' => $this->language->id,
        ]);

        $service->insert([array_values($this->makeRow(['name' => 'New Name']))], $this->headers);

        $this->assertSame(1, Title::count());
        $this->assertSame('old-name', $existing->fresh()->slug);
        $this->assertCount(2, $existing->genres);
    }

    public function test_insert_bulk_gives_same_named_titles_distinct_slugs(): void
    {
        $rows = [
            $this->makeRow(['id' => '1', 'name' => 'Dune', 'release_date' => '1984-12-14']),
            $this->makeRow(['id' => '2', 'name' => 'Dune', 'release_date' => '2021-09-15']),
            $this->makeRow(['id' => '3', 'name' => 'Dune', 'release_date' => '2021-10-22']),
            $this->makeRow(['id' => '3', 'name' => 'Dune', 'type' => 'tv']),
        ];
        $data = array_map(fn ($r) => array_values($r), $rows);

        $count = TitleService::insertBulk($data, $this->headers);

        $this->assertSame(4, $count);
        $this->assertSame(
            ['dune' => 1, 'dune-2021' => 2, 'dune-movie-3' => 3, 'dune-1999' => 3],
            Title::orderBy('id')->get()->mapWithKeys(fn (Title $title): array => [$title->slug => $title->tmdb_id])->all(),
        );
        $this->assertSame(['movie', 'movie', 'movie', 'tv'], Title::orderBy('id')->pluck('type')->map->value->all());
    }

    public function test_insert_bulk_slugs_untransliterable_names_by_type_and_tmdb_id(): void
    {
        $data = [array_values($this->makeRow(['id' => '1720717', 'name' => '洺河往事']))];

        TitleService::insertBulk($data, $this->headers);

        $this->assertDatabaseHas('titles', ['tmdb_id' => 1720717, 'slug' => 'movie-1720717']);
    }

    public function test_insert_bulk_skips_title_that_already_exists_in_database(): void
    {
        Title::factory()->create([
            'slug' => 'the-matrix',
            'tmdb_id' => 12345,
            'type' => 'movie',
            'language_id' => $this->language->id,
        ]);

        $data = [array_values($this->makeRow())];

        $count = TitleService::insertBulk($data, $this->headers);

        $this->assertSame(0, $count);
        $this->assertSame(1, Title::where('slug', 'the-matrix')->count());
    }

    public function test_insert_bulk_reuses_existing_genre_across_rows(): void
    {
        $rows = [
            $this->makeRow(['id' => '1', 'name' => 'Title One', 'genres' => 'Action']),
            $this->makeRow(['id' => '2', 'name' => 'Title Two', 'genres' => 'Action']),
        ];
        $data = array_map(fn ($r) => array_values($r), $rows);

        TitleService::insertBulk($data, $this->headers);

        $this->assertSame(1, Genre::where('slug', 'action')->count());

        $genre = Genre::where('slug', 'action')->first();
        $this->assertSame(2, $genre->titles()->count());
    }

    public function test_insert_bulk_continues_after_a_failed_row(): void
    {
        $rows = [
            $this->makeRow(['id' => '1', 'name' => 'Good Title', 'language_id' => '99999']), // bad FK
            $this->makeRow(['id' => '2', 'name' => 'Another Good Title']),
        ];

        $data = array_map(fn ($r) => array_values($r), $rows);

        $count = TitleService::insertBulk($data, $this->headers);

        //        $this->assertSame(1, $count);
        $this->assertDatabaseHas('titles', ['slug' => 'another-good-title']);
        $this->assertDatabaseMissing('titles', ['slug' => 'good-title']);
    }

    public function test_failed_row_is_logged_once_with_its_exception(): void
    {
        Log::spy();

        $data = [array_values($this->makeRow(['id' => '77', 'name' => 'Broken Title', 'language_id' => '99999']))];

        TitleService::insertBulk($data, $this->headers);

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Title insert failed'
            && $context['tmdb_id'] === 77
            && $context['slug'] === 'broken-title'
            && $context['exception'] instanceof QueryException);
    }
}
