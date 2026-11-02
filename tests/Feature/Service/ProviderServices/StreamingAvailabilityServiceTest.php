<?php

namespace Tests\Feature\Service\ProviderServices;

use App\Domain\Enums\ProviderSource;
use App\Domain\Enums\TitleType;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\Title;
use App\Services\ProviderServices\ProviderCatalog;
use App\Services\ProviderServices\StreamingAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StreamingAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Provider::factory()->withAlias(ProviderSource::STREAMING_AVAILABILITY, 'netflix')->create();
    }

    private function makeOption(array $overrides = []): array
    {
        return array_merge([
            'service' => ['id' => 'prime', 'name' => 'Prime Video'],
            'type' => 'rent',
            'link' => 'https://www.amazon.com/example',
            'quality' => 'hd',
        ], $overrides);
    }

    private function makeShow(array $streamingOptionsByCountry, array $overrides = []): array
    {
        return array_merge([
            'imdbId' => 'tt1234567',
            'tmdbId' => 'movie/1234567',
            'streamingOptions' => $streamingOptionsByCountry,
        ], $overrides);
    }

    private function service(): StreamingAvailabilityService
    {
        return new StreamingAvailabilityService(
            apiKey: 'test-key',
            baseUrl: 'https://movie-night.test',
            catalog: new ProviderCatalog,
        );
    }

    public function test_builds_dto_matching_the_country_regardless_of_iso2_casing(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $amazonProvider = Provider::factory()->withAlias(ProviderSource::STREAMING_AVAILABILITY, 'prime')->create();
        CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $amazonProvider->id]);
        $title = Title::factory()->create(['imdb_id' => 'tt1234567']);

        Http::fake(['movie-night.test/*' => Http::response([
            $this->makeShow(['us' => [$this->makeOption()]]),
        ])]);

        $result = $this->service()->getTopShows($country, 'netflix');

        $this->assertCount(1, $result);
        $dto = $result->first();
        $this->assertSame($amazonProvider->id, $dto->provider_id);
        $this->assertSame($country->id, $dto->country_id);
        $this->assertSame($title->id, $dto->title_id);
        $this->assertSame('HD', $dto->quality);
    }

    public function test_filters_out_addon_rows(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        Provider::factory()->withAlias(ProviderSource::STREAMING_AVAILABILITY, 'prime')->create();
        Title::factory()->create(['imdb_id' => 'tt1234567']);

        Http::fake(['movie-night.test/*' => Http::response([
            $this->makeShow(['us' => [$this->makeOption(['type' => 'addon'])]]),
        ])]);

        $result = $this->service()->getTopShows($country, 'netflix');

        $this->assertCount(0, $result);
    }

    public function test_skips_options_for_a_service_id_with_no_app_mapping(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        Title::factory()->create(['imdb_id' => 'tt1234567']);

        Http::fake(['movie-night.test/*' => Http::response([
            $this->makeShow(['us' => [$this->makeOption(['service' => ['id' => 'unknown_vendor']])]]),
        ])]);

        $result = $this->service()->getTopShows($country, 'netflix');

        $this->assertCount(0, $result);
    }

    public function test_skips_shows_with_no_matching_title(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        Provider::factory()->withAlias(ProviderSource::STREAMING_AVAILABILITY, 'prime')->create();

        Http::fake(['movie-night.test/*' => Http::response([
            $this->makeShow(['us' => [$this->makeOption()]]),
        ])]);

        $result = $this->service()->getTopShows($country, 'netflix');

        $this->assertCount(0, $result);
    }

    public function test_matches_title_by_tmdb_id_and_type_when_imdb_id_is_absent(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $amazon = Provider::factory()->withAlias(ProviderSource::STREAMING_AVAILABILITY, 'prime')->create();
        CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $amazon->id]);
        $title = Title::factory()->create(['imdb_id' => null, 'tmdb_id' => 1234567, 'type' => TitleType::MOVIE->value]);

        Http::fake(['movie-night.test/*' => Http::response([
            $this->makeShow(['us' => [$this->makeOption()]], ['imdbId' => null]),
        ])]);

        $result = $this->service()->getTopShows($country, 'netflix');

        $this->assertCount(1, $result);
        $this->assertSame($title->id, $result->first()->title_id);
    }

    public function test_skips_options_for_a_provider_not_linked_to_the_country(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        Provider::factory()->withAlias(ProviderSource::STREAMING_AVAILABILITY, 'prime')->create();
        Title::factory()->create(['imdb_id' => 'tt1234567']);

        Http::fake(['movie-night.test/*' => Http::response([
            $this->makeShow(['us' => [$this->makeOption()]]),
        ])]);

        $this->assertCount(0, $this->service()->getTopShows($country, 'netflix'));
    }

    public function test_returns_nothing_without_calling_the_api_for_a_service_with_no_provider_alias(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        Http::fake();

        $this->assertCount(0, $this->service()->getTopShows($country, 'unsupported'));
        Http::assertNothingSent();
    }
}
