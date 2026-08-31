<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SimilarTitleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    private function attachSimilar(Title $title, Title $similar, float $score): void
    {
        $title->similarTitles()->attach($similar->id, [
            'score' => $score,
            'reasons' => json_encode([]),
        ]);
    }

    public function test_returns_similar_titles_as_a_resource_collection(): void
    {
        $title = Title::factory()->create();
        $a = Title::factory()->create();
        $a->genres()->attach(Genre::factory()->create());
        $a->keywords()->attach(Keyword::factory()->create());
        $b = Title::factory()->create();

        $this->attachSimilar($title, $a, 0.8);
        $this->attachSimilar($title, $b, 0.6);

        $this->getJson("/title/{$title->id}/similar")
            ->assertOk()
            ->assertJsonCount(2, 'titles')
            ->assertJsonStructure(['titles' => [['id', 'name', 'genres', 'keywords', 'user_state']]])
            ->assertJsonCount(1, 'titles.0.genres')
            ->assertJsonCount(1, 'titles.0.keywords');
    }

    public function test_returns_empty_when_no_similar_titles(): void
    {
        $title = Title::factory()->create();

        $this->getJson("/title/{$title->id}/similar")
            ->assertOk()
            ->assertJsonCount(0, 'titles');
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

    public function test_returns_404_for_unknown_title(): void
    {
        $this->getJson('/title/999999/similar')
            ->assertNotFound();
    }
}
