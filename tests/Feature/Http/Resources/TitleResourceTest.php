<?php

namespace Tests\Feature\Http\Resources;

use App\Models\Genre;
use App\Models\Keyword;
use App\Models\Title;
use App\Models\User;
use App\Models\UserTitleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TitleResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_only_the_whitelisted_title_fields(): void
    {
        $title = Title::factory()->create();
        $title->genres()->attach(Genre::factory()->create());
        $title->keywords()->attach(Keyword::factory()->create());

        $this->getJson("/title/{$title->id}")
            ->assertOk()
            ->assertJsonStructure([
                'title' => [
                    'id', 'name', 'slug', 'type', 'rating', 'release_date', 'runtime',
                    'overview', 'backdrop_path', 'poster_path', 'is_adult',
                    'genres', 'keywords', 'title_providers', 'user_state',
                ],
            ]);
    }

    public function test_it_omits_internal_and_unused_columns(): void
    {
        $title = Title::factory()->create();

        $response = $this->getJson("/title/{$title->id}")->assertOk();

        foreach (['embedding', 'revenue', 'popularity', 'tmdb_id', 'imdb_id', 'status', 'homepage', 'tagline', 'original_name', 'language_id'] as $field) {
            $response->assertJsonMissingPath("title.{$field}");
        }
    }

    public function test_it_formats_release_date_as_a_year(): void
    {
        $title = Title::factory()->create(['release_date' => '2019-06-01']);

        $this->getJson("/title/{$title->id}")
            ->assertOk()
            ->assertJsonPath('title.release_date', '2019');
    }

    public function test_user_state_is_null_for_guests(): void
    {
        $title = Title::factory()->create();
        UserTitleState::factory()->create(['title_id' => $title->id]);

        $this->getJson("/title/{$title->id}")
            ->assertOk()
            ->assertJsonPath('title.user_state', null);
    }

    public function test_user_state_is_null_when_the_authenticated_user_has_no_row(): void
    {
        $title = Title::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->getJson("/title/{$title->id}")
            ->assertOk()
            ->assertJsonPath('title.user_state', null);
    }

    public function test_user_state_reflects_the_authenticated_users_row(): void
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();
        UserTitleState::factory()->create([
            'user_id' => $user->id,
            'title_id' => $title->id,
            'rate' => 4.5,
            'liked' => true,
            'watched' => true,
            'to_watch' => false,
        ]);
        $this->actingAs($user);

        $this->getJson("/title/{$title->id}")
            ->assertOk()
            ->assertJsonPath('title.user_state.rate', 4.5)
            ->assertJsonPath('title.user_state.watched', true)
            ->assertJsonPath('title.user_state.to_watch', false)
            ->assertJsonPath('title.user_state.liked', true);
    }

    public function test_user_state_liked_reflects_the_column_not_the_rating(): void
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();
        UserTitleState::factory()->create([
            'user_id' => $user->id,
            'title_id' => $title->id,
            'rate' => 5,
            'liked' => false,
        ]);
        $this->actingAs($user);

        $this->getJson("/title/{$title->id}")
            ->assertOk()
            ->assertJsonPath('title.user_state.liked', false);
    }

    public function test_user_state_does_not_leak_another_users_row(): void
    {
        $title = Title::factory()->create();
        UserTitleState::factory()->create([
            'user_id' => User::factory()->create()->id,
            'title_id' => $title->id,
            'watched' => true,
        ]);
        $this->actingAs(User::factory()->create());

        $this->getJson("/title/{$title->id}")
            ->assertOk()
            ->assertJsonPath('title.user_state', null);
    }
}
