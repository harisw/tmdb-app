<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Keyword;
use App\Models\Title;
use App\Models\User;
use App\Models\UserTitleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AvoidedKeywordControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Bus::fake();
    }

    public function test_guests_cannot_avoid_keywords(): void
    {
        $keyword = Keyword::factory()->create();

        $this->post(route('avoided-tags.store', $keyword))->assertRedirect(route('login'));

        $this->assertDatabaseCount('user_avoided_keywords', 0);
    }

    public function test_store_avoids_a_keyword_once_even_when_repeated(): void
    {
        $user = User::factory()->create();
        $keyword = Keyword::factory()->create();

        $this->actingAs($user)
            ->from(route('settings'))
            ->post(route('avoided-tags.store', $keyword))
            ->assertRedirect(route('settings'));
        $this->actingAs($user)->post(route('avoided-tags.store', $keyword));

        $this->assertSame([$keyword->id], $user->avoidedKeywords()->pluck('keywords.id')->all());
    }

    public function test_store_rejects_a_keyword_past_the_limit(): void
    {
        $user = User::factory()->create();
        $user->avoidedKeywords()->attach(Keyword::factory(User::MAX_AVOIDED_KEYWORDS)->create()->modelKeys());

        $this->actingAs($user)
            ->post(route('avoided-tags.store', Keyword::factory()->create()))
            ->assertSessionHasErrors('keyword');

        $this->assertSame(User::MAX_AVOIDED_KEYWORDS, $user->avoidedKeywords()->count());
    }

    public function test_store_accepts_an_already_avoided_keyword_at_the_limit(): void
    {
        $user = User::factory()->create();
        $keywords = Keyword::factory(User::MAX_AVOIDED_KEYWORDS)->create();
        $user->avoidedKeywords()->attach($keywords->modelKeys());

        $this->actingAs($user)
            ->post(route('avoided-tags.store', $keywords->first()))
            ->assertSessionHasNoErrors();
    }

    public function test_store_returns_not_found_for_an_unknown_keyword(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('avoided-tags.store', 'no-such-tag'))
            ->assertNotFound();
    }

    public function test_destroy_stops_avoiding_only_that_keyword(): void
    {
        $user = User::factory()->create();
        [$kept, $removed] = Keyword::factory(2)->create();
        $user->avoidedKeywords()->attach([$kept->id, $removed->id]);

        $this->actingAs($user)->delete(route('avoided-tags.destroy', $removed));

        $this->assertSame([$kept->id], $user->avoidedKeywords()->pluck('keywords.id')->all());
    }

    public function test_avoided_titles_leave_browse_but_stay_reachable_by_direct_link_and_in_the_users_lists(): void
    {
        $user = User::factory()->create();
        $zombie = Keyword::factory()->create();
        $avoided = Title::factory()->create();
        $kept = Title::factory()->create();
        $avoided->keywords()->attach($zombie->id);
        $user->avoidedKeywords()->attach($zombie->id);
        UserTitleState::factory()->create(['user_id' => $user->id, 'title_id' => $avoided->id, 'to_watch' => true]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('titles.data', fn ($titles) => collect($titles)->pluck('id')->all() === [$kept->id])
            );

        $this->actingAs($user)
            ->getJson(route('title-show', $avoided))
            ->assertOk()
            ->assertJsonPath('title.id', $avoided->id);

        $this->actingAs($user)
            ->get(route('user.show-title-list', 'to_watch'))
            ->assertInertia(fn ($page) => $page->where('titles.data.0.id', $avoided->id));
    }
}
