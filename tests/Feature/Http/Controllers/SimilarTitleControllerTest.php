<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimilarTitleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function attachSimilar(Title $title, Title $similar, float $score): void
    {
        $title->similarTitles()->attach($similar->id, [
            'score' => $score,
            'reasons' => json_encode([]),
        ]);
    }

    public function test_returns_similar_titles_json(): void
    {
        $title = Title::factory()->create();
        $a = Title::factory()->create();
        $b = Title::factory()->create();

        $this->attachSimilar($title, $a, 0.8);
        $this->attachSimilar($title, $b, 0.6);

        $this->getJson("/title/{$title->id}/similar")
            ->assertOk()
            ->assertJsonCount(2, 'titles')
            ->assertJsonStructure(['titles' => [['id', 'name', 'slug', 'poster_path', 'genres', 'keywords', 'title_providers']]]);
    }

    public function test_returns_empty_when_no_similar_titles(): void
    {
        $title = Title::factory()->create();

        $this->getJson("/title/{$title->id}/similar")
            ->assertOk()
            ->assertJsonCount(0, 'titles');
    }

    public function test_similar_titles_are_ordered_by_score_descending(): void
    {
        $title = Title::factory()->create();
        $low = Title::factory()->create();
        $high = Title::factory()->create();

        $this->attachSimilar($title, $low, 0.5);
        $this->attachSimilar($title, $high, 0.9);

        $this->getJson("/title/{$title->id}/similar")
            ->assertOk()
            ->assertJsonPath('titles.0.id', $high->id)
            ->assertJsonPath('titles.1.id', $low->id);
    }

    public function test_similar_titles_are_capped_at_twelve(): void
    {
        $title = Title::factory()->create();

        Title::factory(15)->create()->each(
            fn (Title $t) => $this->attachSimilar($title, $t, 0.8)
        );

        $this->getJson("/title/{$title->id}/similar")
            ->assertOk()
            ->assertJsonCount(12, 'titles');
    }

    public function test_similar_titles_eager_load_genres_and_keywords(): void
    {
        $title = Title::factory()->create();
        $similar = Title::factory()->create();
        $similar->genres()->attach(Genre::factory()->create());
        $similar->keywords()->attach(Keyword::factory()->create());

        $this->attachSimilar($title, $similar, 0.8);

        $this->getJson("/title/{$title->id}/similar")
            ->assertOk()
            ->assertJsonCount(1, 'titles.0.genres')
            ->assertJsonCount(1, 'titles.0.keywords');
    }

    public function test_payload_omits_the_embedding_vector(): void
    {
        $title = Title::factory()->create();
        $similar = Title::factory()->create();
        $this->attachSimilar($title, $similar, 0.8);

        $this->getJson("/title/{$title->id}/similar")
            ->assertOk()
            ->assertJsonMissingPath('titles.0.embedding');
    }

    public function test_returns_404_for_unknown_title(): void
    {
        $this->getJson('/title/999999/similar')
            ->assertNotFound();
    }
}
