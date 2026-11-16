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

    /**
     * @param  list<array<string, mixed>>  $keywords
     * @return Collection<int, array{name: string}>
     */
    private function prepareKeywords(array $keywords): Collection
    {
        return collect($keywords)
            ->pluck('name')
            ->map(fn (mixed $name) => trim((string) $name))
            ->filter(fn (string $name) => $name !== '')
            ->uniqueStrict()
            ->values()
            ->map(fn (string $name) => ['name' => $name]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return list<array<string, mixed>>
     */
    private function getKeywordData(?array $data): array
    {
        return $data['keywords'] ?? $data['results'] ?? [];
    }

    private function isUsedInSimilarity(string $slug): bool
    {
        return ! preg_match('/^\d{4}$/', $slug);
    }
}
