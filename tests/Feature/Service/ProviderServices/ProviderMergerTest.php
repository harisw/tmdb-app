<?php

namespace Tests\Feature\Service\ProviderServices;

use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use App\Services\ProviderServices\ProviderMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProviderMergerTest extends TestCase
{
    use RefreshDatabase;

    private function offer(CountryProvider $countryProvider, Title $title, StreamType $type = StreamType::FLATRATE): TitleProvider
    {
        return TitleProvider::factory()->create([
            'country_provider_id' => $countryProvider->id,
            'country_id' => $countryProvider->country_id,
            'provider_id' => $countryProvider->provider_id,
            'title_id' => $title->id,
            'type' => $type->value,
        ]);
    }

    public function test_moves_aliases_and_deletes_the_merged_provider(): void
    {
        $variant = Provider::factory()->withTmdbId(2100)->create();
        $main = Provider::factory()->withTmdbId(119)->create();

        (new ProviderMerger)->merge($variant, $main);

        $this->assertModelMissing($variant);
        $this->assertEqualsCanonicalizing(['119', '2100'], $main->aliases()->pluck('external_id')->all());
    }

    public function test_moves_country_links_the_target_does_not_have_yet(): void
    {
        $country = Country::factory()->create();
        $variant = Provider::factory()->create();
        $main = Provider::factory()->create();
        $countryProvider = CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $variant->id]);
        $offer = $this->offer($countryProvider, Title::factory()->create());

        (new ProviderMerger)->merge($variant, $main);

        $this->assertSame($main->id, $countryProvider->fresh()->provider_id);
        $this->assertSame($main->id, $offer->fresh()->provider_id);
    }

    public function test_moves_offers_onto_the_targets_existing_country_link(): void
    {
        $country = Country::factory()->create();
        $variant = Provider::factory()->create();
        $main = Provider::factory()->create();
        $variantLink = CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $variant->id]);
        $mainLink = CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $main->id]);
        $offer = $this->offer($variantLink, Title::factory()->create(), StreamType::ADS);

        (new ProviderMerger)->merge($variant, $main);

        $this->assertModelMissing($variantLink);
        $this->assertSame($mainLink->id, $offer->fresh()->country_provider_id);
        $this->assertSame($main->id, $offer->fresh()->provider_id);
    }

    public function test_drops_offers_the_target_already_has_for_the_same_title_and_type(): void
    {
        $country = Country::factory()->create();
        $title = Title::factory()->create();
        $variant = Provider::factory()->create();
        $main = Provider::factory()->create();
        $variantOffer = $this->offer(CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $variant->id]), $title);
        $mainOffer = $this->offer(CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $main->id]), $title);

        (new ProviderMerger)->merge($variant, $main);

        $this->assertModelMissing($variantOffer);
        $this->assertModelExists($mainOffer);
    }

    public function test_recounts_the_targets_titles(): void
    {
        $country = Country::factory()->create();
        $variant = Provider::factory()->create(['count' => 1]);
        $main = Provider::factory()->create(['count' => 1]);
        $this->offer(CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $variant->id]), Title::factory()->create());
        $this->offer(CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $main->id]), Title::factory()->create());

        (new ProviderMerger)->merge($variant, $main);

        $this->assertSame(2, $main->fresh()->count);
    }

    public function test_refuses_to_merge_a_provider_into_itself(): void
    {
        $provider = Provider::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        (new ProviderMerger)->merge($provider, $provider);
    }
}
