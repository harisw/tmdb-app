<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CountryProvider>
 */
class CountryProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'provider_id' => Provider::factory(),
            'priority' => 0,
        ];
    }
}
