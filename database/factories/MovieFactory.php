<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Movie>
 */
class MovieFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(3),
            'rating' => fake()->randomFloat(1, 1, 10),
            'status' => 'Released',
            'release_date' => fake()->date(),
            'revenue' => fake()->numberBetween(0, 1_000_000_000),
            'runtime' => fake()->numberBetween(60, 180),
            'img_backdrop' => null,
            'img_poster' => null,
            'overview' => fake()->paragraph(),
            'popularity' => fake()->randomFloat(2, 0, 100),
            'tagline' => fake()->sentence(),
            'language_id' => Language::factory(),
        ];
    }
}
