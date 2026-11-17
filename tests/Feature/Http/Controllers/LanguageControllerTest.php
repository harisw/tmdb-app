<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Language;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class LanguageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    public function test_languages_page_lists_languages_with_titles_and_filters_by_lang_param(): void
    {
        $en = Language::factory()->create(['code' => 'en']);
        $fr = Language::factory()->create(['code' => 'fr']);
        Title::factory(2)->create(['language_id' => $en->id]);
        Title::factory()->create(['language_id' => $fr->id]);
        Language::factory(2)->create(); // no titles -> excluded from the list

        $this->get('/languages?lang=en')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleByLanguage')
                ->has('languages', 2)
                ->has('titles.data', 2)
            );
    }

    public function test_languages_page_returns_no_titles_without_a_lang_param(): void
    {
        $this->get('/languages')
            ->assertInertia(fn ($page) => $page
                ->component('TitleByLanguage')
                ->where('titles', [])
            );
    }
}
