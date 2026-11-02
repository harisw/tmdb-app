<?php

namespace Tests\Feature\Service\ProviderServices;

use App\Domain\Enums\ProviderSource;
use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\Provider;
use App\Models\Title;
use App\Services\ProviderServices\ProviderCatalog;
use App\Services\ProviderServices\WatchmodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WatchmodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSource(array $overrides = []): array
    {
        return array_merge([
            'source_id' => 349,
            'name' => 'AppleTV',
            'type' => 'rent',
            'region' => 'US',
            'web_url' => 'https://tv.apple.com/us/movie/example',
            'format' => 'HD',
            'price' => 5.99,
            'seasons' => 0,
            'episodes' => 0,
        ], $overrides);
    }

    private function service(): WatchmodeService
    {
        return new WatchmodeService(
            apiKey: 'test-key',
            baseUrl: 'https://watchmode.test',
            catalog: new ProviderCatalog,
        );
    }

    public function test_builds_dto_for_a_mapped_provider_and_country(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->withAlias(ProviderSource::WATCHMODE, 'AppleTV')->create();
        CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $provider->id]);
        $title = Title::factory()->create(['tmdb_id' => 1550569, 'imdb_id' => null]);

        Http::fake(['watchmode.test/*' => Http::response([$this->makeSource()])]);

        $result = $this->service()->getProviders($title);

        $this->assertCount(1, $result);
        $dto = $result->first();
        $this->assertSame($provider->id, $dto->provider_id);
        $this->assertSame($country->id, $dto->country_id);
        $this->assertSame($title->id, $dto->title_id);
        $this->assertSame(StreamType::RENT, $dto->type);
        $this->assertSame('HD', $dto->quality);
        $this->assertSame('https://tv.apple.com/us/movie/example', $dto->link);
    }

    #[DataProvider('sourceMappings')]
    public function test_normalizes_type_and_quality(array $source, StreamType $type, string $quality): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->withAlias(ProviderSource::WATCHMODE, 'Peacock')->create();
        CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $provider->id]);
        $title = Title::factory()->create(['tmdb_id' => 1550569]);

        Http::fake(['watchmode.test/*' => Http::response([
            $this->makeSource(['name' => 'Peacock', ...$source]),
        ])]);

        $dto = $this->service()->getProviders($title)->first();

        $this->assertSame($type, $dto->type);
        $this->assertSame($quality, $dto->quality);
    }

    /**
     * @return array<string, array{array<string, string>, StreamType, string}>
     */
    public static function sourceMappings(): array
    {
        return [
            'sub becomes flatrate' => [['type' => 'sub', 'format' => 'HD'], StreamType::FLATRATE, 'HD'],
            'uppercase 4K format' => [['type' => 'rent', 'format' => '4K'], StreamType::RENT, '4k'],
        ];
    }

    public function test_skips_entries_for_a_provider_name_with_no_app_mapping(): void
    {
        Country::factory()->create(['iso2' => 'US']);
        $title = Title::factory()->create(['tmdb_id' => 1550569]);

        Http::fake(['watchmode.test/*' => Http::response([
            $this->makeSource(['name' => 'Some Unmapped Service']),
        ])]);

        $result = $this->service()->getProviders($title);

        $this->assertCount(0, $result);
    }

    public function test_skips_entries_for_an_unseeded_region_without_crashing(): void
    {
        Provider::factory()->withAlias(ProviderSource::WATCHMODE, 'AppleTV')->create();
        $title = Title::factory()->create(['tmdb_id' => 1550569]);

        Http::fake(['watchmode.test/*' => Http::response([
            $this->makeSource(['region' => 'ZZ']),
        ])]);

        $result = $this->service()->getProviders($title);

        $this->assertCount(0, $result);
    }

    public function test_deduplicates_identical_rows(): void
    {
        $country = Country::factory()->create(['iso2' => 'US']);
        $provider = Provider::factory()->withAlias(ProviderSource::WATCHMODE, 'AppleTV')->create();
        CountryProvider::factory()->create(['country_id' => $country->id, 'provider_id' => $provider->id]);
        $title = Title::factory()->create(['tmdb_id' => 1550569]);

        Http::fake(['watchmode.test/*' => Http::response([
            $this->makeSource(),
            $this->makeSource(),
        ])]);

        $result = $this->service()->getProviders($title);

        $this->assertCount(1, $result);
    }

    public function test_does_not_call_the_api_when_title_has_no_external_ids(): void
    {
        $title = Title::factory()->create(['tmdb_id' => null, 'imdb_id' => null]);

        Http::fake();

        $result = $this->service()->getProviders($title);

        $this->assertCount(0, $result);
        Http::assertNothingSent();
    }
}
