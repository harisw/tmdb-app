<?php

namespace App\Jobs;

use App\Models\Country;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;

class EnrichTopTitlesProvidersBatchJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $jobs = Country::whereIn('iso2', array_keys(EnrichTopTitlesProvidersJob::PROVIDER_SLUGS_BY_COUNTRY))
            ->get()
            ->map(
                fn (Country $country) => new EnrichTopTitlesProvidersJob($country)->delay(now()->addMinute())
            );

        Bus::batch($jobs)
            ->name('Enrich Top Titles Providers Batch')
            // ->allowFailures()
            ->dispatch();
    }
}
