<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EnrichTitleProvidersLinkBatchJob;
use App\Jobs\EnrichTitleProvidersLinkJob;
use App\Models\Title;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class EnrichTitleProvidersLinkBatchJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_batches_provider_links_on_the_enrichment_queue(): void
    {
        Bus::fake();
        Title::factory()->create();

        (new EnrichTitleProvidersLinkBatchJob(10))->handle();

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->queue() === 'enrichment'
            && $batch->jobs->first() instanceof EnrichTitleProvidersLinkJob);
    }
}
