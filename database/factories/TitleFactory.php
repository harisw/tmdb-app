<?php

namespace Database\Factories;

use App\Domain\Enums\TitleType;
use App\Models\Language;
use App\Models\Title;
use App\Services\KnowledgeGraph\OpenAIEmbeddingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Title>
 */
class TitleFactory extends Factory
{
    private const EMBEDDING_DIMENSIONS = 1536;

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
        ];
    }

    /**
     * @param  array<int, float>  $weights  dimension => value, every other dimension is 0
     */
    public function embedded(array $weights = [0 => 1.0]): static
    {
        $vector = array_replace(array_fill(0, self::EMBEDDING_DIMENSIONS, 0.0), $weights);

        return $this->state(['embedding' => OpenAIEmbeddingService::vectorArrayToLiteral($vector)]);
    }
}
