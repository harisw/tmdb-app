<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Country;
use App\Models\Keyword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('settings'))->assertRedirect(route('login'));
    }

    public function test_edit_renders_settings_with_avoided_keywords_in_the_order_they_were_added(): void
    {
        $user = User::factory()->create();
        $zombie = Keyword::factory()->create(['name' => 'zombie']);
        $gore = Keyword::factory()->create(['name' => 'gore']);

        $user->avoidedKeywords()->attach($zombie->id, ['created_at' => now()->subDay()]);
        $user->avoidedKeywords()->attach($gore->id, ['created_at' => now()]);

        $this->actingAs($user)
            ->get(route('settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings')
                ->where('maxAvoidedKeywords', User::MAX_AVOIDED_KEYWORDS)
                ->where('avoidedKeywords', fn ($keywords) => collect($keywords)->pluck('name')->all() === ['zombie', 'gore'])
                ->missing('keywordResults')
            );
    }

    public function test_keyword_results_rank_prefix_matches_first_then_by_title_count(): void
    {
        Keyword::factory()->create(['name' => 'zombie apocalypse', 'count' => 5]);
        Keyword::factory()->create(['name' => 'nazi zombie', 'count' => 90]);
        Keyword::factory()->create(['name' => 'zombie', 'count' => 40]);
        Keyword::factory()->create(['name' => 'zombie child', 'count' => 0]);
        Keyword::factory()->create(['name' => 'vampire', 'count' => 70]);

        $this->actingAs(User::factory()->create())
            ->get(route('settings', ['tag_query' => ' Zombie ']))
            ->assertInertia(fn ($page) => $page->reloadOnly('keywordResults', fn ($reload) => $reload
                ->where('keywordResults.query', 'Zombie')
                ->where(
                    'keywordResults.items',
                    fn ($items) => collect($items)->pluck('name')->all() === ['zombie', 'zombie apocalypse', 'nazi zombie']
                )
            ));
    }

    public function test_keyword_results_treat_like_wildcards_literally(): void
    {
        Keyword::factory()->create(['name' => 'zombie', 'count' => 10]);
        Keyword::factory()->create(['name' => '100% true story', 'count' => 3]);

        $this->actingAs(User::factory()->create())
            ->get(route('settings', ['tag_query' => '%']))
            ->assertInertia(fn ($page) => $page->reloadOnly('keywordResults', fn ($reload) => $reload
                ->where('keywordResults.items', fn ($items) => collect($items)->pluck('name')->all() === ['100% true story'])
            ));
    }

    public function test_keyword_results_are_empty_for_a_blank_query(): void
    {
        Keyword::factory()->create(['name' => 'zombie', 'count' => 10]);

        $this->actingAs(User::factory()->create())
            ->get(route('settings'))
            ->assertInertia(fn ($page) => $page->reloadOnly('keywordResults', fn ($reload) => $reload
                ->where('keywordResults.query', '')
                ->has('keywordResults.items', 0)
            ));
    }

    public function test_keyword_query_longer_than_fifty_characters_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings', ['tag_query' => str_repeat('a', 51)]))
            ->assertSessionHasErrors('tag_query');
    }

    public function test_update_saves_the_profile(): void
    {
        $user = User::factory()->create(['hide_adult' => true]);
        $country = Country::factory()->create();

        $this->actingAs($user)
            ->from(route('settings'))
            ->patch(route('profile.update'), [
                'username' => 'new_name',
                'country' => $country->iso2,
                'avatar_style' => 'bottts',
                'hide_adult' => false,
            ])
            ->assertRedirect(route('settings'));

        $user->refresh();
        $this->assertSame('new_name', $user->username);
        $this->assertSame($country->id, $user->country_id);
        $this->assertFalse($user->hide_adult);
        $this->assertSame('bottts', $user->extra_attributes->get('avatar.style'));
    }

    public function test_update_rejects_a_username_taken_by_someone_else(): void
    {
        User::factory()->create(['username' => 'taken']);
        $country = Country::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('profile.update'), [
                'username' => 'taken',
                'country' => $country->iso2,
                'avatar_style' => 'bottts',
                'hide_adult' => true,
            ])
            ->assertSessionHasErrors('username');
    }
}
