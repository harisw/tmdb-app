<?php

namespace App\Pipelines\Stages;

use App\Data\Contexts\EnrichmentContext;
use App\Services\TmdbApiService;
use App\Services\WatchProviderSyncService;
use Closure;

class ProcessWatchProviders
{
    public function __construct(
        private readonly TmdbApiService $tmdb,
        private readonly WatchProviderSyncService $sync,
    ) {}

    public function handle(EnrichmentContext $ctx, Closure $next): mixed
    {
        $response = $this->tmdb->getWatchProviders($ctx->title->type, $ctx->title->tmdb_id);
        if (isset($response['results']) && is_array($response['results'])) {
            $this->sync->sync($ctx->title, $response['results']);
        }

        return $next($ctx);
    }
}
