<?php

namespace Database\Factories;

use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TitleProvider>
 */
class TitleProviderFactory extends Factory
{
    public function definition(): array
    {
        $country = Country::factory()->create();
        $provider = Provider::factory()->create();
        $countryProvider = CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);

        return [
            'country_provider_id' => $countryProvider->id,
            'country_id' => $country->id,
            'provider_id' => $provider->id,
            'title_id' => Title::factory(),
            'type' => StreamType::FLATRATE->value,
            'tmdb_link' => fake()->url(),
            'priority' => 0,
            'audio_languages' => ['en'],
            'subtitle_languages' => [],
        ];
    }
}
