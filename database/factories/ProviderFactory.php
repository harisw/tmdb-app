<?php

namespace Database\Factories;

use App\Domain\Enums\ProviderSource;
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
            'slug' => Str::slug($name),
            'name' => $name,
            'img_logo' => null,
            'priority' => 0,
        ];
    }

    public function withAlias(ProviderSource $source, int|string $externalId): static
    {
        return $this->afterCreating(fn (Provider $provider) => $provider->aliases()->create([
            'source' => $source,
            'external_id' => (string) $externalId,
            'name' => $provider->name,
        ]));
    }

    public function withTmdbId(int $tmdbId): static
    {
        return $this->withAlias(ProviderSource::TMDB, $tmdbId);
    }
}
