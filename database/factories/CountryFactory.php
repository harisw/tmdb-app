<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->country(),
            'iso2' => strtoupper(fake()->unique()->lexify('??')),
            'iso3' => null,
            'language_id' => null,
        ];
    }
}
