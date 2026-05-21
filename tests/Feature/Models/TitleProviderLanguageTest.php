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
        $tpl = TitleProviderLanguage::factory()->create(['type' => ProviderLanguageType::AUDIO->value]);

        $this->assertInstanceOf(ProviderLanguageType::class, $tpl->fresh()->type);
    }

    public function test_audio_type_resolves_correctly(): void
    {
        $tpl = TitleProviderLanguage::factory()->create(['type' => ProviderLanguageType::AUDIO->value]);

        $this->assertSame(ProviderLanguageType::AUDIO, $tpl->fresh()->type);
    }

    public function test_subtitle_type_resolves_correctly(): void
    {
        $tpl = TitleProviderLanguage::factory()->create(['type' => ProviderLanguageType::SUBTITLE->value]);

        $this->assertSame(ProviderLanguageType::SUBTITLE, $tpl->fresh()->type);
    }

    public function test_title_provider_relationship_returns_title_provider(): void
    {
        $tp = TitleProvider::factory()->create();
        $tpl = TitleProviderLanguage::factory()->create(['title_provider_id' => $tp->id]);

        $this->assertInstanceOf(TitleProvider::class, $tpl->titleProvider);
        $this->assertSame($tp->id, $tpl->titleProvider->id);
    }

    public function test_language_relationship_returns_language(): void
    {
        $language = Language::factory()->create();
        $tpl = TitleProviderLanguage::factory()->create(['language_id' => $language->id]);

        $this->assertInstanceOf(Language::class, $tpl->language);
        $this->assertSame($language->id, $tpl->language->id);
    }
}
