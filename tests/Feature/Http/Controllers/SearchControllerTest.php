<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Genre;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_search_all_filters_by_genre_without_a_query(): void
    {
        Bus::fake();
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
}
