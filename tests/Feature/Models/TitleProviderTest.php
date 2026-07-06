<?php

namespace Tests\Feature\Models;

use App\Domain\Enums\ProviderLanguageType;
use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use App\Models\TitleProviderLanguage;
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
        $tp = $this->makeTitleProvider(['type' => StreamType::RENT->value]);

        $this->assertInstanceOf(StreamType::class, $tp->fresh()->type);
    }

    public function test_stream_type_rent_resolves_correctly(): void
    {
        $tp = $this->makeTitleProvider(['type' => StreamType::RENT->value]);

        $this->assertSame(StreamType::RENT, $tp->fresh()->type);
    }

    public function test_stream_type_buy_resolves_correctly(): void
    {
        $tp = $this->makeTitleProvider(['type' => StreamType::BUY->value]);

        $this->assertSame(StreamType::BUY, $tp->fresh()->type);
    }

    public function test_stream_type_flatrate_resolves_correctly(): void
    {
        $tp = $this->makeTitleProvider(['type' => StreamType::FLATRATE->value]);

        $this->assertSame(StreamType::FLATRATE, $tp->fresh()->type);
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

    // --- Relationships ---

    public function test_title_relationship_returns_title(): void
    {
        $title = Title::factory()->create();
        $tp = $this->makeTitleProvider(['title_id' => $title->id]);

        $this->assertInstanceOf(Title::class, $tp->title);
        $this->assertSame($title->id, $tp->title->id);
    }

    public function test_country_relationship_returns_country(): void
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

        $this->assertInstanceOf(Country::class, $tp->country);
        $this->assertSame($country->id, $tp->country->id);
    }

    public function test_provider_relationship_returns_provider(): void
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

        $this->assertInstanceOf(Provider::class, $tp->provider);
        $this->assertSame($provider->id, $tp->provider->id);
    }

    public function test_country_provider_relationship_returns_country_provider(): void
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

        $this->assertInstanceOf(CountryProvider::class, $tp->countryProvider);
        $this->assertSame($countryProvider->id, $tp->countryProvider->id);
    }

    public function test_provider_languages_relationship_returns_collection(): void
    {
        $tp = $this->makeTitleProvider();

        TitleProviderLanguage::factory()->create([
            'title_provider_id' => $tp->id,
            'type' => ProviderLanguageType::AUDIO->value,
        ]);
        TitleProviderLanguage::factory()->create([
            'title_provider_id' => $tp->id,
            'type' => ProviderLanguageType::SUBTITLE->value,
        ]);

        $this->assertCount(2, $tp->providerLanguages);
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

    public function test_deleting_title_provider_cascades_to_provider_languages(): void
    {
        $tp = $this->makeTitleProvider();
        $tpl = TitleProviderLanguage::factory()->create(['title_provider_id' => $tp->id]);

        $tp->delete();

        $this->assertDatabaseMissing('title_provider_languages', ['id' => $tpl->id]);
    }
}
