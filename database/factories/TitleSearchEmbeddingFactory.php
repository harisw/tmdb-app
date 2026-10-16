<?php

namespace Database\Factories;

use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\Search\SearchDocumentBuilder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TitleSearchEmbedding>
 */
class TitleSearchEmbeddingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title_id' => Title::factory(),
            'embedding' => TitleSearchEmbedding::toVectorLiteral(self::unitVector(fake()->numberBetween(0, TitleSearchEmbedding::DIMENSIONS - 1))),
            'document_version' => SearchDocumentBuilder::VERSION,
        ];
    }

    public function pointingAt(int $dimension): static
    {
        return $this->state(['embedding' => TitleSearchEmbedding::toVectorLiteral(self::unitVector($dimension))]);
    }

    /**
     * @return array<int, float>
     */
    public static function unitVector(int $dimension): array
    {
        $vector = array_fill(0, TitleSearchEmbedding::DIMENSIONS, 0.0);
        $vector[$dimension] = 1.0;

        return $vector;
    }
}
