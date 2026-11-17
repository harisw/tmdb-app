<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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

    public function test_index_defers_the_constellation_until_requested(): void
    {
        $seed = Title::factory()->create(['popularity' => 100]);
        Title::factory(4)->create()->each(fn (Title $neighbour) => $seed->similarTitles()
            ->attach($neighbour->id, ['score' => 0.9, 'reasons' => []]));

        $this->get('/')->assertInertia(fn ($page) => $page
            ->missing('constellation')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('constellation.seed', $seed->id)
                ->has('constellation.nodes', 5)
                ->has('constellation.links', 4)
            )
        );
    }

    public function test_index_rejects_a_seed_outside_the_pool(): void
    {
        $this->get('/?seed=99')->assertSessionHasErrors('seed');
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

    public function test_index_lists_most_popular_titles_first(): void
    {
        $niche = Title::factory()->create(['popularity' => 5, 'created_at' => now()]);
        $hit = Title::factory()->create(['popularity' => 900, 'created_at' => now()->subYear()]);
        $middling = Title::factory()->create(['popularity' => 50, 'created_at' => now()->subMonth()]);

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('titles.data.0.id', $hit->id)
                ->where('titles.data.1.id', $middling->id)
                ->where('titles.data.2.id', $niche->id)
            );
    }
}
