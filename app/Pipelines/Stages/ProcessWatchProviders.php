<?php

namespace App\Pipelines\Stages;

use App\Data\DTOs\TmdbApi\TmdbKeywordData;
use App\Data\EnrichmentContext;
use App\Models\Country;
use App\Models\Keyword;
use App\Services\TmdbService;
use Closure;

class ProcessWatchProviders
{

    public function __construct(private readonly TmdbService $tmdb)
    {
    }

    public function handle(EnrichmentContext $ctx, Closure $next): mixed
    {
        $results = $this->tmdb->getWatchProviders($ctx->title->type, $ctx->title->tmdb_id)['results'];


        foreach ($results as $iso2 => $result) {
            try {
                $country = Country::where('iso2', $iso2)->first();
                $tmdbLink = $result->link;
                unset($result->link);
                foreach ($result as $streamType => $providers) {
                    $uniqueProviders = collect($providers)->unique('provider_id')
                    ->map(function ($provider) {
                        return $provider->country_provider_id = ;
                    });
                }
            } catch (\Exception $e) {
                \Log::error($e->getMessage());
            }
        }
        $ctx->keywordIds = TmdbKeywordData::collect(collect($keywords)->unique('name'))
            ->toBase()
            ->map(fn(TmdbKeywordData $kw) => Keyword::firstOrCreate(
                ['slug' => $kw->slug],
                ['name' => $kw->name]
            )->id);

        return $next($ctx);
    }
}
