<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin access used to be derived from `username === ADMIN_USERNAME`. Because
 * /profile lets a user pick their own username, that meant anyone could rename
 * their way into Horizon, Pulse and the feature flag panel on any database
 * where the name was unclaimed. These tests pin that path shut.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.admin_username' => 'the_operator']);
    }

    public function test_non_admin_cannot_reach_feature_flag_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/feature-flags')
            ->assertForbidden();
    }

    public function test_guest_cannot_reach_feature_flag_panel(): void
    {
        $this->get('/admin/feature-flags')->assertRedirect();
    }

    public function test_admin_can_reach_feature_flag_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/feature-flags')
            ->assertOk();
    }

    public function test_holding_the_admin_username_does_not_grant_access(): void
    {
        // The heart of it: even if the reserved name is somehow on the row,
        // authorization comes from the column.
        $user = User::factory()->create(['username' => 'the_operator']);

        $this->assertFalse($user->is_admin);

        $this->actingAs($user)->get('/admin/feature-flags')->assertForbidden();
    }

    public function test_user_cannot_rename_themselves_to_the_admin_username(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create(['country_id' => $country->id]);

        $this->actingAs($user)
            ->patch('/profile', [
                'username' => 'the_operator',
                'country' => $country->iso2,
                'avatar_style' => 'adventurer',
            ])
            ->assertSessionHasErrors('username');

        $this->assertNotSame('the_operator', $user->fresh()->username);
    }

    public function test_admin_can_still_save_their_own_profile(): void
    {
        $country = Country::factory()->create();
        $admin = User::factory()->admin()->create([
            'username' => 'the_operator',
            'country_id' => $country->id,
        ]);

        $this->actingAs($admin)
            ->patch('/profile', [
                'username' => 'the_operator',
                'country' => $country->iso2,
                'avatar_style' => 'adventurer',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_is_admin_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();

        $user->fill(['is_admin' => true]);

        $this->assertFalse((bool) $user->is_admin);
    }

    public function test_non_admin_cannot_toggle_a_feature_flag(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch('/admin/feature-flags/knowledge-graph-enrichment', ['active' => true])
            ->assertForbidden();
    }

    public function test_admin_can_toggle_a_feature_flag(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/feature-flags/knowledge-graph-enrichment', ['active' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('features', [
            'name' => 'knowledge-graph-enrichment',
            'scope' => 'global',
            'value' => 'true',
        ]);
    }

    public function test_unknown_feature_flag_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/feature-flags/not-a-real-flag', ['active' => true])
            ->assertNotFound();
    }
}
