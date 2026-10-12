<?php

namespace App\Pipelines\Stages;

use App\Data\Contexts\EnrichmentContext;
use App\Data\DTOs\TmdbApi\TmdbKeywordData;
use App\Models\Keyword;
use App\Services\TmdbApiService;
use Closure;
use Illuminate\Support\Collection;

class ProcessKeywords
{
    public function __construct(private readonly TmdbApiService $tmdb) {}

    public function handle(EnrichmentContext $ctx, Closure $next): mixed
    {
        $keywords = $this->getKeywordData($this->tmdb->getKeywords($ctx->title->type, $ctx->title->tmdb_id));

        $keywordIds = TmdbKeywordData::collect($this->prepareKeywords($keywords))
            ->toBase()
            ->map(fn (TmdbKeywordData $kw) => Keyword::firstOrCreate(
                ['slug' => $kw->slug],
                [
                    'name' => $kw->name,
                    'use_in_similarity' => $this->isUsedInSimilarity($kw->slug),
                ]
            )->id);
        $ctx->title->keywords()->sync($keywordIds);

        return $next($ctx);
    }

    private function prepareKeywords(array $keywords): Collection
    {
        return collect($keywords)->only('name')->unique()->map(fn ($k) => trim($k))->filter();
    }

    private function getKeywordData($data): array
    {
        return $data['keywords'] ?? $data['results'] ?? [];
    }

    private function isUsedInSimilarity(string $slug): bool
    {
        return ! preg_match('/^\d{4}$/', $slug);
    }
}
