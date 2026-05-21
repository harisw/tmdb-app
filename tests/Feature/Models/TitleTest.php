<?php

namespace Tests\Feature\Models;

use App\Domain\Enums\TitleType;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_is_cast_to_title_type_enum(): void
    {
        $title = Title::factory()->create(['type' => TitleType::MOVIE->value]);

        $this->assertInstanceOf(TitleType::class, $title->fresh()->type);
    }

    public function test_type_movie_resolves_to_correct_enum_case(): void
    {
        $title = Title::factory()->create(['type' => TitleType::MOVIE->value]);

        $this->assertSame(TitleType::MOVIE, $title->fresh()->type);
    }

    public function test_type_tv_resolves_to_correct_enum_case(): void
    {
        $title = Title::factory()->create(['type' => TitleType::TV->value]);

        $this->assertSame(TitleType::TV, $title->fresh()->type);
    }

    public function test_factory_defaults_to_movie_type(): void
    {
        $title = Title::factory()->create();

        $this->assertSame(TitleType::MOVIE, $title->fresh()->type);
    }

    public function test_can_filter_by_type(): void
    {
        Title::factory(2)->create(['type' => TitleType::MOVIE->value]);
        Title::factory()->create(['type' => TitleType::TV->value]);

        $movies = Title::where('type', TitleType::MOVIE->value)->get();
        $shows = Title::where('type', TitleType::TV->value)->get();

        $this->assertCount(2, $movies);
        $this->assertCount(1, $shows);
    }
}
