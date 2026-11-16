<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EnrichTitleJob;
use App\Jobs\EnrichTitleProvidersBatchJob;
use App\Models\Title;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class EnrichTitleProvidersBatchJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_batches_title_enrichment_on_the_enrichment_queue(): void
    {
        Bus::fake();
        Title::factory()->create(['tmdb_id' => 1, 'enriched_at' => null]);

        (new EnrichTitleProvidersBatchJob(10))->handle();

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->queue() === 'enrichment'
            && $batch->jobs->first() instanceof EnrichTitleJob);
    }
}
