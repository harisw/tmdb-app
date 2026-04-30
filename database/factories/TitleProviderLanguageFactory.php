<?php

namespace Database\Factories;

use App\Domain\Enums\ProviderLanguageType;
use App\Models\Language;
use App\Models\TitleProvider;
use App\Models\TitleProviderLanguage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TitleProviderLanguage>
 */
class TitleProviderLanguageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title_provider_id' => TitleProvider::factory(),
            'language_id' => Language::factory(),
            'type' => ProviderLanguageType::AUDIO->value,
        ];
    }
}
