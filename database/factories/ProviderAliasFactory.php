<?php

namespace Database\Factories;

use App\Domain\Enums\ProviderSource;
use App\Models\Provider;
use App\Models\ProviderAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderAlias>
 */
class ProviderAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'source' => ProviderSource::TMDB,
            'external_id' => (string) fake()->unique()->randomNumber(5),
            'name' => fake()->company(),
        ];
    }
}
