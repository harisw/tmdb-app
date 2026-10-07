<?php

namespace App\Jobs;

use App\Models\Title;
use App\Services\ProviderServices\TitleProviderWriter;
use App\Services\ProviderServices\WatchmodeService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnrichTitleProvidersLinkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(private readonly Title $title)
    {
        $this->onQueue('enrichment');
    }

    public function handle(WatchmodeService $service, TitleProviderWriter $writer): void
    {
        Log::info("Enriching {$this->title->slug} Title");
        $dtos = $service->getProviders($this->title);
        if ($dtos->isEmpty()) {
            return;
        }

        $writer->upsert($dtos);
        $this->title->update([
            'enriched_at' => now(),
        ]);
    }
}
