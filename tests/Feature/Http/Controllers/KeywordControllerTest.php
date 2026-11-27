<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Keyword;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class KeywordControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    public function test_tags_page_lists_keywords_and_filters_by_tag_param(): void
    {
        $keyword = Keyword::factory()->create(['slug' => 'space-opera', 'count' => 10]);
        $titles = Title::factory(3)->create();
        $keyword->titles()->attach($titles->pluck('id'));
        Title::factory()->create(); // no keyword

        $this->get('/tags?tag=space-opera')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AllKeywords')
                ->has('tags')
                ->has('titles.data', 3)
            );
    }

    public function test_tags_page_returns_no_titles_without_a_tag_param(): void
    {
        $this->get('/tags')
            ->assertInertia(fn ($page) => $page
                ->component('AllKeywords')
                ->where('titles', [])
            );
    }

    public function test_show_cursor_pages_through_titles_added_at_the_same_time_without_skips_or_repeats(): void
    {
        $keyword = Keyword::factory()->create(['slug' => 'heist', 'count' => 35]);
        $titles = Title::factory(35)->create(['created_at' => now()]);
        $keyword->titles()->attach($titles->pluck('id'));

        $first = $this->get('/tag/heist')->inertiaProps('titles');
        $second = $this->get($first['links']['next'])->inertiaProps('titles');

        $this->assertCount(30, $first['data']);
        $this->assertCount(5, $second['data']);
        $this->assertNull($second['links']['next']);
        $this->assertEqualsCanonicalizing(
            $titles->pluck('id')->all(),
            collect([...$first['data'], ...$second['data']])->pluck('id')->all()
        );
    }
}
