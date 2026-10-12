<?php

namespace Tests\Feature\Models\Scopes;

use App\Domain\Enums\TitleType;
use App\Models\Scopes\AdultContentScope;
use App\Models\Scopes\TitleTypeScope;
use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TitleScopesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    // ---------------------------------------------------------------------
    // TitleTypeScope — filters by the `title_type` request query param
    // ---------------------------------------------------------------------

    public function test_title_type_scope_filters_by_request_param(): void
    {
        Title::factory(2)->create(['type' => TitleType::MOVIE->value]);
        Title::factory()->create(['type' => TitleType::TV->value]);

        request()->merge(['title_type' => TitleType::MOVIE->value]);

        $this->assertCount(2, Title::all());
    }

    public function test_title_type_scope_is_inert_without_request_param(): void
    {
        Title::factory(2)->create(['type' => TitleType::MOVIE->value]);
        Title::factory()->create(['type' => TitleType::TV->value]);

        $this->assertCount(3, Title::all());
    }

    public function test_title_type_scope_can_be_bypassed(): void
    {
        Title::factory(2)->create(['type' => TitleType::MOVIE->value]);
        Title::factory()->create(['type' => TitleType::TV->value]);

        request()->merge(['title_type' => TitleType::MOVIE->value]);

        $this->assertCount(3, Title::withoutGlobalScope(TitleTypeScope::class)->get());
    }

    // ---------------------------------------------------------------------
    // AdultContentScope — hides adult titles based on the user's preference
    // ---------------------------------------------------------------------

    public function test_adult_scope_hides_adult_titles_for_guests(): void
    {
        Title::factory(2)->create(['is_adult' => false]);
        Title::factory()->create(['is_adult' => true]);

        // No authenticated user -> defaults to hiding adult content.
        $this->assertCount(2, Title::all());
    }

    public function test_adult_scope_hides_adult_titles_when_user_opts_in(): void
    {
        Title::factory(2)->create(['is_adult' => false]);
        Title::factory()->create(['is_adult' => true]);

        $this->actingAs(User::factory()->create(['hide_adult' => true]));

        $this->assertCount(2, Title::all());
    }

    public function test_adult_scope_shows_adult_titles_when_user_opts_out(): void
    {
        Title::factory(2)->create(['is_adult' => false]);
        Title::factory()->create(['is_adult' => true]);

        $this->actingAs(User::factory()->create(['hide_adult' => false]));

        $this->assertCount(3, Title::all());
    }

    public function test_adult_scope_can_be_bypassed(): void
    {
        Title::factory(2)->create(['is_adult' => false]);
        Title::factory()->create(['is_adult' => true]);

        $this->actingAs(User::factory()->create(['hide_adult' => true]));

        $this->assertCount(3, Title::withoutGlobalScope(AdultContentScope::class)->get());
    }
}
