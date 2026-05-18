<?php

namespace App\Pipelines\Stages;

use App\Data\EnrichmentContext;
use App\Domain\Enums\StreamType;
use App\Models\Country;
use App\Models\CountryProvider;
use App\Models\TitleProvider;
use App\Services\TmdbService;
use Closure;
use Log;

class ProcessWatchProviders
{
    public function __construct(private readonly TmdbService $tmdb) {}

    public function handle(EnrichmentContext $ctx, Closure $next): mixed
    {
        $results = $this->tmdb->getWatchProviders($ctx->title->type, $ctx->title->tmdb_id)['results'];

        $validStreamTypes = collect(StreamType::cases())->map->values()->all();
        $countryMap = Country::pluck('id', 'iso2');

        $countryProvidersMap = CountryProvider::with('provider:tmdb_id')
            ->select('id', 'country_id', 'provider_id')
            ->get()
            ->keyBy(fn ($cp) => "{$cp->country_id}-{$cp->provider->tmdb_id}");
        $rows = [];

        foreach ($results as $iso2 => $countryData) {
            try {
                $countryId = $countryMap->get($iso2);
                if (! $countryId) {
                    Log::warning(get_class($this).": unknown Country [$iso2]");

                    continue;
                }

                $tmdbLink = $countryData['link'];
                unset($countryData['link']);
                foreach ($countryData as $streamType => $providers) {
                    if (! in_array($streamType, $validStreamTypes, true)) {
                        Log::warning(get_class($this).": unknown StreamType [$streamType]");

                        continue;
                    }

                    foreach (collect($providers)->unique('provider_id') as $providerRaw) {
                        $tmdbProviderId = $providerRaw['provider_id'];
                        $countryProvider = $countryProvidersMap->get("$countryId-$tmdbProviderId");
                        if (! $countryProvider) {
                            Log::warning(get_class($this).': unknown CountryProvider '.json_encode($providerRaw));

                            continue;
                        }

                        $rows[] = [
                            'title_id' => $ctx->title->id,
                            'country_provider_id' => $countryProvider->id,
                            'country_id' => $countryId,
                            'provider_id' => $countryProvider->provider_id,
                            'type' => $streamType,
                            'tmdb_link' => $tmdbLink,
                            'priority' => $providerRaw['display_priority'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }

        if (! empty($rows)) {
            TitleProvider::where('title_id', $ctx->title->id)->delete();
            foreach (array_chunk($rows, 500) as $chunkedRows) {
                TitleProvider::insert($chunkedRows);
            }
        }

        return $next($ctx);
    }
}
