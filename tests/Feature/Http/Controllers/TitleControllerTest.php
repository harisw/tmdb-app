<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TitleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_title_with_everything_the_modal_renders(): void
    {
        $title = Title::factory()->create();
        $title->genres()->attach(Genre::factory()->create());
        $title->keywords()->attach(Keyword::factory()->create());

        $this->getJson("/title/{$title->id}")
            ->assertOk()
            ->assertJsonPath('title.id', $title->id)
            ->assertJsonStructure([
                'title' => [
                    'id', 'name', 'slug', 'overview', 'backdrop_path', 'poster_path',
                    'release_date', 'rating', 'genres', 'keywords', 'title_providers',
                ],
            ])
            ->assertJsonCount(1, 'title.genres')
            ->assertJsonCount(1, 'title.keywords');
    }

    public function test_payload_omits_the_embedding_vector(): void
    {
        $title = Title::factory()->create();

        $this->getJson("/title/{$title->id}")
            ->assertOk()
            ->assertJsonMissingPath('title.embedding');
    }

    public function test_returns_404_for_unknown_title(): void
    {
        $this->getJson('/title/999999')
            ->assertNotFound();
    }
}
