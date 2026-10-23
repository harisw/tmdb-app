<?php

namespace Tests\Feature\Models\Scopes;

use App\Domain\Enums\TitleType;
use App\Models\Keyword;
use App\Models\Scopes\AvoidedKeywordScope;
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
    // AvoidedKeywordScope — hides titles tagged with the user's avoided keywords
    // ---------------------------------------------------------------------

    public function test_avoided_keyword_scope_hides_titles_with_any_avoided_keyword(): void
    {
        [$zombie, $gore, $comedy] = Keyword::factory(3)->create();
        $kept = Title::factory()->create();
        Title::factory()->create()->keywords()->attach($zombie->id);
        Title::factory()->create()->keywords()->attach([$gore->id, $comedy->id]);
        $kept->keywords()->attach($comedy->id);

        $user = User::factory()->create();
        $user->avoidedKeywords()->attach([$zombie->id, $gore->id]);
        $this->actingAs($user);

        $this->assertSame([$kept->id], Title::pluck('id')->all());
    }

    public function test_avoided_keyword_scope_is_inert_when_nothing_is_avoided(): void
    {
        Title::factory(2)->create()->each(fn (Title $title) => $title->keywords()->attach(Keyword::factory()->create()));

        $this->actingAs(User::factory()->create());

        $this->assertCount(2, Title::all());
    }

    public function test_avoided_keyword_scope_only_applies_the_authenticated_users_list(): void
    {
        $zombie = Keyword::factory()->create();
        Title::factory()->create()->keywords()->attach($zombie->id);
        User::factory()->create()->avoidedKeywords()->attach($zombie->id);

        $this->assertCount(1, Title::all());

        $this->actingAs(User::factory()->create());

        $this->assertCount(1, Title::all());
    }

    public function test_avoided_keyword_scope_can_be_bypassed(): void
    {
        $zombie = Keyword::factory()->create();
        Title::factory()->create()->keywords()->attach($zombie->id);

        $user = User::factory()->create();
        $user->avoidedKeywords()->attach($zombie->id);
        $this->actingAs($user);

        $this->assertCount(1, Title::withoutGlobalScope(AvoidedKeywordScope::class)->get());
    }
}
