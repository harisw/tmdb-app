<?php

namespace Tests\Feature\Jobs;

use App\Domain\Enums\ProviderSource;
use App\Jobs\ImportTmdbProvidersJob;
use App\Models\Provider;
use App\Models\ProviderAlias;
use App\Services\ProviderServices\ProviderCatalogImporter;
use App\Services\TmdbApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ImportTmdbProvidersJobTest extends TestCase
{
    use RefreshDatabase;

    private function snapshot(string $type): string
    {
        return file_get_contents(database_path("seeders/data/{$type}-providers.json"));
    }

    private function runJob(): void
    {
        (new ImportTmdbProvidersJob)->handle(app(TmdbApiService::class), app(ProviderCatalogImporter::class));
    }

    private function provider(ProviderSource $source, int|string $externalId): Provider
    {
        return ProviderAlias::where('source', $source)->where('external_id', (string) $externalId)->sole()->provider;
    }

    public function test_imports_the_movie_and_tv_provider_lists_with_the_curated_aliases(): void
    {
        Http::fake([
            '*/3/watch/providers/movie' => Http::response($this->snapshot('movie')),
            '*/3/watch/providers/tv' => Http::response($this->snapshot('tv')),
        ]);

        $this->runJob();

        $prime = $this->provider(ProviderSource::TMDB, 119);
        $this->assertTrue($prime->is($this->provider(ProviderSource::TMDB, 2100)));
        $this->assertTrue($prime->is($this->provider(ProviderSource::WATCHMODE, 'Amazon')));
        $this->assertFalse($this->provider(ProviderSource::TMDB, 80)->is($this->provider(ProviderSource::TMDB, 526)));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '3/watch/providers/tv'));
    }

    public function test_imports_nothing_when_tmdb_fails_to_return_a_list(): void
    {
        Http::fake([
            '*/3/watch/providers/movie' => Http::response($this->snapshot('movie')),
            '*/3/watch/providers/tv' => Http::response(null, 500),
        ]);

        $this->expectException(RuntimeException::class);

        try {
            $this->runJob();
        } finally {
            $this->assertDatabaseCount('providers', 0);
        }
    }

    public function test_runs_on_the_enrichment_queue(): void
    {
        $this->assertSame('enrichment', (new ImportTmdbProvidersJob)->queue);
    }
}
