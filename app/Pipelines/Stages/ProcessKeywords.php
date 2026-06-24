<?php

namespace App\Pipelines\Stages;

use App\Data\DTOs\TmdbApi\TmdbKeywordData;
use App\Data\EnrichmentContext;
use App\Models\Keyword;
use App\Services\TmdbService;
use Closure;

class ProcessKeywords
{
    public function __construct(private readonly TmdbService $tmdb) {}

    public function handle(EnrichmentContext $ctx, Closure $next): mixed
    {
        $keywords = $this->getKeywordData($this->tmdb->getKeywords($ctx->title->type, $ctx->title->tmdb_id));

        $keywordIds = TmdbKeywordData::collect(collect($keywords)->unique('name'))
            ->toBase()
            ->map(fn (TmdbKeywordData $kw) => Keyword::firstOrCreate(
                ['slug' => $kw->slug],
                ['name' => $kw->name]
            )->id);
        $ctx->title->keywords()->sync($keywordIds);

        return $next($ctx);
    }

    private function getKeywordData($data): array
    {
        return $data['keywords'] ?? $data['results'] ?? [];
    }
}
