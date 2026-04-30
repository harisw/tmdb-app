<?php

namespace Tests\Feature\Service;

use App\Models\Genre;
use App\Models\Language;
use App\Models\Title;
use App\Services\TitleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
            'runtime', 'img_backdrop', 'imdb_id', 'overview', 'popularity',
            'img_poster', 'tagline', 'language_id', 'homepage',
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
            'img_backdrop' => '/backdrop.jpg',
            'imdb_id' => 'tt0133093',
            'overview' => 'A computer hacker learns the truth.',
            'popularity' => '85.4',
            'img_poster' => '/poster.jpg',
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

    public function test_insert_bulk_skips_duplicate_slug(): void
    {
        $rows = [
            $this->makeRow(['id' => '1', 'name' => 'The Matrix']),
            $this->makeRow(['id' => '2', 'name' => 'The Matrix']), // same slug
        ];
        $data = array_map(fn ($r) => array_values($r), $rows);

        $count = TitleService::insertBulk($data, $this->headers);

        $this->assertSame(1, $count);
        $this->assertSame(1, Title::where('slug', 'the-matrix')->count());
    }

    public function test_insert_bulk_skips_title_that_already_exists_in_database(): void
    {
        Title::factory()->create(['slug' => 'the-matrix', 'language_id' => $this->language->id]);

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

    public function test_insert_bulk_tracks_progress_in_cache(): void
    {
        $uploadId = 'test-upload-123';
        Cache::put("csv_upload_{$uploadId}", [
            'total_rows' => 1,
            'processed_rows' => 0,
            'status' => 'processing',
        ]);

        $data = [array_values($this->makeRow())];

        TitleService::insertBulk($data, $this->headers, $uploadId);

        $progress = Cache::get("csv_upload_{$uploadId}");
        $this->assertSame('completed', $progress['status']);
        $this->assertSame(1, $progress['processed_rows']);
    }

    public function test_insert_bulk_marks_progress_as_failed_on_error(): void
    {
        $uploadId = 'test-upload-456';
        Cache::put("csv_upload_{$uploadId}", [
            'total_rows' => 1,
            'processed_rows' => 0,
            'status' => 'processing',
        ]);

        $rows = [$this->makeRow(['language_id' => '99999'])]; // invalid FK
        $data = array_map(fn ($r) => array_values($r), $rows);

        TitleService::insertBulk($data, $this->headers, $uploadId);

        $progress = Cache::get("csv_upload_{$uploadId}");
        $this->assertSame('failed', $progress['status']);
        $this->assertNotNull($progress['error']);
    }

    public function test_insert_bulk_returns_zero_when_all_rows_are_duplicates(): void
    {
        Title::factory()->create(['slug' => 'the-matrix', 'language_id' => $this->language->id]);

        $data = [array_values($this->makeRow())];

        $count = TitleService::insertBulk($data, $this->headers);

        $this->assertSame(0, $count);
    }

    public function test_insert_bulk_handles_multiple_titles(): void
    {
        $rows = [
            $this->makeRow(['id' => '1', 'name' => 'Film Alpha']),
            $this->makeRow(['id' => '2', 'name' => 'Film Beta']),
            $this->makeRow(['id' => '3', 'name' => 'Film Gamma']),
        ];
        $data = array_map(fn ($r) => array_values($r), $rows);

        $count = TitleService::insertBulk($data, $this->headers);

        $this->assertSame(3, $count);
        $this->assertSame(3, Title::count());
    }
}
