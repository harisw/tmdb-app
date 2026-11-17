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
        foreach ([TitleType::MOVIE, TitleType::TV] as $type) {
            $title = Title::factory()->create(['type' => $type->value]);

            $this->assertSame($type, $title->fresh()->type);
        }
    }

    public function test_factory_defaults_to_movie_type(): void
    {
        $title = Title::factory()->create();

        $this->assertSame(TitleType::MOVIE, $title->fresh()->type);
    }
}
