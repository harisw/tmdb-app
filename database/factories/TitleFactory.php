<?php

namespace Database\Factories;

use App\Domain\Enums\TitleType;
use App\Models\Language;
use App\Models\Title;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Title>
 */
class TitleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(3),
            'type' => TitleType::MOVIE->value,
            'rating' => fake()->randomFloat(1, 1, 10),
            'status' => 'Released',
            'release_date' => fake()->date(),
            'revenue' => fake()->numberBetween(0, 1_000_000_000),
            'runtime' => fake()->numberBetween(60, 180),
            'backdrop_path' => null,
            'poster_path' => null,
            'overview' => fake()->paragraph(),
            'popularity' => fake()->randomFloat(2, 0, 100),
            'tagline' => fake()->sentence(),
            'language_id' => Language::factory(),
            'is_adult' => false,
        ];
    }
}
