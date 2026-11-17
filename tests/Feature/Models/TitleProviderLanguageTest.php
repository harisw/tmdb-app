<?php

namespace Tests\Feature\Models;

use App\Domain\Enums\ProviderLanguageType;
use App\Models\Language;
use App\Models\TitleProvider;
use App\Models\TitleProviderLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TitleProviderLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_is_cast_to_provider_language_type_enum(): void
    {
        foreach ([ProviderLanguageType::AUDIO, ProviderLanguageType::SUBTITLE] as $type) {
            $tpl = TitleProviderLanguage::factory()->create(['type' => $type->value]);

            $this->assertSame($type, $tpl->fresh()->type);
        }
    }

    // --- Cascade deletes ---

    public function test_deleting_title_provider_cascades_to_title_provider_language(): void
    {
        $tp = TitleProvider::factory()->create();
        $tpl = TitleProviderLanguage::factory()->create(['title_provider_id' => $tp->id]);

        $tp->delete();

        $this->assertDatabaseMissing('title_provider_languages', ['id' => $tpl->id]);
    }

    public function test_deleting_language_cascades_to_title_provider_language(): void
    {
        $language = Language::factory()->create();
        $tpl = TitleProviderLanguage::factory()->create(['language_id' => $language->id]);

        $language->delete();

        $this->assertDatabaseMissing('title_provider_languages', ['id' => $tpl->id]);
    }
}
