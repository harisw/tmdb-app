<?php

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'tmdb_provider_id' => fake()->unique()->randomNumber(5),
            'slug' => Str::slug($name),
            'name' => $name,
            'img_logo' => null,
            'priority' => 0,
        ];
    }
}
