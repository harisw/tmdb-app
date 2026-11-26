<?php

namespace Tests\Feature\Http\Controllers;

use App\Domain\Enums\TitleType;
use App\Http\Requests\OnboardingRequest;
use App\Models\Genre;
use App\Models\Title;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Bus::fake();

        $this->user = User::factory()->create();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('onboarding'))->assertRedirect(route('login'));
        $this->post(route('onboarding.store'), ['title_ids' => [1, 2, 3]])->assertRedirect(route('login'));
    }

    public function test_show_offers_each_embedded_title_once_across_genres(): void
    {
        $drama = Genre::factory()->create(['count' => 10]);
        $comedy = Genre::factory()->create(['count' => 5]);
        $shared = Title::factory()->embedded()->create(['popularity' => 100]);
        $dramaOnly = Title::factory()->embedded()->create(['popularity' => 50]);
        $unembedded = Title::factory()->create(['popularity' => 900]);
        $drama->titles()->attach([$shared->id, $dramaOnly->id, $unembedded->id]);
        $comedy->titles()->attach([$shared->id]);

        $this->actingAs($this->user)
            ->get(route('onboarding'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Onboarding')
                ->has('titles.data', 1)
                ->where('titles.data.0.id', $shared->id)
                ->where('round', 0)
                ->where('minPicks', OnboardingRequest::MIN_PICKS)
                ->where('maxPicks', OnboardingRequest::MAX_PICKS)
            );
    }

    public function test_show_ignores_the_title_type_toggle(): void
    {
        $genre = Genre::factory()->create(['count' => 10]);
        $series = Title::factory()->embedded()->create(['type' => TitleType::TV->value]);
        $genre->titles()->attach($series);

        $this->actingAs($this->user)
            ->get(route('onboarding', ['title_type' => TitleType::MOVIE->value]))
            ->assertInertia(fn ($page) => $page
                ->has('titles.data', 1)
                ->where('titles.data.0.id', $series->id)
            );
    }

    public function test_show_steps_down_each_genre_by_round(): void
    {
        $drama = Genre::factory()->create(['count' => 10]);
        $comedy = Genre::factory()->create(['count' => 5]);
        $dramaFirst = Title::factory()->embedded()->create(['popularity' => 100]);
        $dramaSecond = Title::factory()->embedded()->create(['popularity' => 50]);
        $comedyFirst = Title::factory()->embedded()->create(['popularity' => 80]);
        $comedySecond = Title::factory()->embedded()->create(['popularity' => 40]);
        $drama->titles()->attach([$dramaFirst->id, $dramaSecond->id]);
        $comedy->titles()->attach([$comedyFirst->id, $comedySecond->id]);

        $this->actingAs($this->user)
            ->get(route('onboarding', ['round' => 1]))
            ->assertInertia(fn ($page) => $page
                ->where('round', 1)
                ->has('titles.data', 2)
                ->where('titles.data.0.id', $dramaSecond->id)
                ->where('titles.data.1.id', $comedySecond->id)
            );
    }

    public function test_show_wraps_rounds_back_to_the_most_popular(): void
    {
        $genre = Genre::factory()->create(['count' => 10]);
        $title = Title::factory()->embedded()->create();
        $genre->titles()->attach($title);

        foreach ([RecommendationService::ONBOARDING_ROUNDS, -3] as $round) {
            $this->actingAs($this->user)
                ->get(route('onboarding', ['round' => $round]))
                ->assertInertia(fn ($page) => $page
                    ->where('round', 0)
                    ->where('titles.data.0.id', $title->id)
                );
        }
    }

    public function test_store_likes_each_pick_and_computes_the_taste(): void
    {
        $picks = Title::factory(3)->embedded()->create();

        $this->actingAs($this->user)
            ->post(route('onboarding.store'), ['title_ids' => $picks->pluck('id')->all()])
            ->assertRedirect('/');

        foreach ($picks as $pick) {
            $this->assertDatabaseHas('user_title_states', ['user_id' => $this->user->id, 'title_id' => $pick->id, 'liked' => true]);
        }
        $this->assertDatabaseHas('user_tastes', ['user_id' => $this->user->id]);
    }

    public function test_store_needs_enough_picks(): void
    {
        $picks = Title::factory(OnboardingRequest::MIN_PICKS - 1)->create();

        $this->actingAs($this->user)
            ->post(route('onboarding.store'), ['title_ids' => $picks->pluck('id')->all()])
            ->assertSessionHasErrors('title_ids');

        $this->assertDatabaseCount('user_title_states', 0);
    }

    public function test_store_caps_the_number_of_picks(): void
    {
        $picks = Title::factory(OnboardingRequest::MAX_PICKS + 1)->create();

        $this->actingAs($this->user)
            ->post(route('onboarding.store'), ['title_ids' => $picks->pluck('id')->all()])
            ->assertSessionHasErrors('title_ids');
    }

    public function test_store_rejects_duplicate_and_unknown_titles(): void
    {
        $title = Title::factory()->create();

        $this->actingAs($this->user)
            ->post(route('onboarding.store'), ['title_ids' => [$title->id, $title->id, 999999]])
            ->assertSessionHasErrors(['title_ids.0', 'title_ids.1', 'title_ids.2']);
    }
}
