<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();
        $tmdbId = fake()->unique()->numberBetween(1, 9_999_999);

        return [
            'tmdb_id' => $tmdbId,
            'name' => $name,
            'slug' => Str::slug($name).'-'.$tmdbId,
            'gender' => fake()->randomElement(['female', 'male', 'unknown']),
            'is_adult' => false,
            'profile_path' => '/'.fake()->regexify('[A-Za-z0-9]{27}').'.jpg',
            'popularity' => fake()->randomFloat(4, 0, 100),
        ];
    }

    public function adult(): static
    {
        return $this->state(fn () => ['is_adult' => true]);
    }
}
