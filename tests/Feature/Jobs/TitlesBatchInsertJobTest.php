<?php

namespace Tests\Feature\Jobs;

use App\Jobs\TitlesBatchInsertJob;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TitlesBatchInsertJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_the_seed_csv_from_the_configured_s3_key(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('seeds/titles.csv', "tmdb_id|name\n");
        config([
            'services.seed.csv_local' => 'does-not-exist.csv',
            'services.seed.csv_s3_key' => 'seeds/titles.csv',
        ]);

        (new TitlesBatchInsertJob)->handle();

        $this->assertDatabaseCount('titles', 0);
    }

    public function test_fails_when_no_seed_csv_is_configured(): void
    {
        config([
            'services.seed.csv_local' => 'does-not-exist.csv',
            'services.seed.csv_s3_key' => null,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not open seed CSV');

        (new TitlesBatchInsertJob)->handle();
    }
}
