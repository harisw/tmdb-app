<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Title;
use App\Models\User;
use App\Models\UserTitleState;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
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
        DB::enableQueryLog();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('titles.data', 30)
                ->where('titles.meta.per_page', 30)
                ->missing('titles.meta.total')
                ->whereNot('titles.meta.next_cursor', null)
            );

        $this->assertNoCountQueryRan();
    }

    public function test_index_cursor_pages_through_tied_popularity_without_skips_or_repeats(): void
    {
        $titles = Title::factory(35)->create(['popularity' => 50]);

        $first = $this->get('/')->inertiaProps('titles');
        $second = $this->get($first['links']['next'])->inertiaProps('titles');

        $this->assertCount(30, $first['data']);
        $this->assertCount(5, $second['data']);
        $this->assertNull($second['links']['next']);
        $this->assertEqualsCanonicalizing(
            $titles->pluck('id')->all(),
            collect([...$first['data'], ...$second['data']])->pluck('id')->all()
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

    public function test_index_is_not_personalised_without_a_taste(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertInertia(fn ($page) => $page->where('personalised', false));
    }

    public function test_index_is_not_personalised_while_the_taste_lacks_signal(): void
    {
        $user = User::factory()->create();
        app(UserService::class)->computeUserTaste($user);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn ($page) => $page->where('personalised', false));
    }

    public function test_index_ranks_unseen_titles_by_closeness_to_the_users_taste(): void
    {
        $user = User::factory()->create();
        $liked = Title::factory()->embedded([0 => 1.0])->create();
        $far = Title::factory()->embedded([1 => 1.0])->create(['popularity' => 900]);
        $near = Title::factory()->embedded([0 => 0.9, 1 => 0.1])->create(['popularity' => 1]);
        Title::factory()->create(['popularity' => 1000]);
        $alsoLiked = Title::factory()->embedded([0 => 1.0])->create();
        foreach ([$liked, $alsoLiked] as $title) {
            UserTitleState::factory()->create(['user_id' => $user->id, 'title_id' => $title->id, 'liked' => true]);
        }
        app(UserService::class)->computeUserTaste($user);
        DB::enableQueryLog();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('personalised', true)
                ->has('titles.data', 2)
                ->where('titles.data.0.id', $near->id)
                ->where('titles.data.1.id', $far->id)
                ->missing('titles.meta.total')
                ->where('titles.links.next', null)
            );

        $this->assertNoCountQueryRan();
    }

    private function assertNoCountQueryRan(): void
    {
        $countQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $sql): bool => str_contains($sql, 'count(*) as "aggregate"'));

        $this->assertEmpty($countQueries, 'Home ran a pagination count: '.$countQueries->implode(' | '));
    }
}
