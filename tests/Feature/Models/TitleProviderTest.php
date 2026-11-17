<?php

namespace Tests\Feature\Models;

use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TitleProviderTest extends TestCase
{
    use RefreshDatabase;

    private function makeTitleProvider(array $overrides = []): TitleProvider
    {
        $country = Country::factory()->create();
        $provider = Provider::factory()->create();
        $countryProvider = CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);

        return TitleProvider::factory()->create(array_merge([
            'country_provider_id' => $countryProvider->id,
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ], $overrides));
    }

    // --- StreamType casts ---

    public function test_type_is_cast_to_stream_type_enum(): void
    {
        foreach ([StreamType::RENT, StreamType::BUY, StreamType::FLATRATE] as $type) {
            $tp = $this->makeTitleProvider(['type' => $type->value]);

            $this->assertSame($type, $tp->fresh()->type);
        }
    }

    // --- JSON array casts ---

    public function test_audio_languages_is_cast_to_array(): void
    {
        $tp = $this->makeTitleProvider(['audio_languages' => ['en', 'fr', 'de']]);

        $this->assertIsArray($tp->fresh()->audio_languages);
        $this->assertSame(['en', 'fr', 'de'], $tp->fresh()->audio_languages);
    }

    public function test_subtitle_languages_is_cast_to_array(): void
    {
        $tp = $this->makeTitleProvider(['subtitle_languages' => ['en', 'es']]);

        $this->assertIsArray($tp->fresh()->subtitle_languages);
        $this->assertSame(['en', 'es'], $tp->fresh()->subtitle_languages);
    }

    public function test_empty_subtitle_languages_returns_empty_array(): void
    {
        $tp = $this->makeTitleProvider(['subtitle_languages' => []]);

        $this->assertSame([], $tp->fresh()->subtitle_languages);
    }

    // --- Cascade deletes ---

    public function test_deleting_title_cascades_to_title_provider(): void
    {
        $title = Title::factory()->create();
        $tp = $this->makeTitleProvider(['title_id' => $title->id]);

        $title->delete();

        $this->assertDatabaseMissing('title_providers', ['id' => $tp->id]);
    }

    public function test_deleting_country_cascades_to_title_provider(): void
    {
        $country = Country::factory()->create();
        $provider = Provider::factory()->create();
        $countryProvider = CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $tp = TitleProvider::factory()->create([
            'country_provider_id' => $countryProvider->id,
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);

        $country->delete();

        $this->assertDatabaseMissing('title_providers', ['id' => $tp->id]);
    }

    public function test_deleting_provider_cascades_to_title_provider(): void
    {
        $country = Country::factory()->create();
        $provider = Provider::factory()->create();
        $countryProvider = CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $tp = TitleProvider::factory()->create([
            'country_provider_id' => $countryProvider->id,
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);

        $provider->delete();

        $this->assertDatabaseMissing('title_providers', ['id' => $tp->id]);
    }

    public function test_deleting_country_provider_cascades_to_title_provider(): void
    {
        $country = Country::factory()->create();
        $provider = Provider::factory()->create();
        $countryProvider = CountryProvider::factory()->create([
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);
        $tp = TitleProvider::factory()->create([
            'country_provider_id' => $countryProvider->id,
            'country_id' => $country->id,
            'provider_id' => $provider->id,
        ]);

        $countryProvider->delete();

        $this->assertDatabaseMissing('title_providers', ['id' => $tp->id]);
    }
}
