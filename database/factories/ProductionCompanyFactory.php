<?php

namespace Database\Factories;

use App\Models\ProductionCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionCompany>
 */
class ProductionCompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(2),
            'count' => 0,
        ];
    }
}
