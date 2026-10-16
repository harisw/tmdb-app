<?php

namespace App\Services\Search;

use App\Models\SearchableTitle;
use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\KnowledgeGraph\OpenAIEmbeddingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HybridSearchService
{
    private const RRF_K = 60;

    public function __construct(private readonly OpenAIEmbeddingService $embeddingService) {}

    /**
     * @return Collection<int, int>
     */
    public function hybridIds(string $text, int $limit = 50): Collection
    {
        $fullTextIds = $this->fullTextIds($text, $limit);

        try {
            $semanticIds = $this->semanticIds($text, $limit);
        } catch (ConnectionException|RuntimeException $e) {
            Log::warning('Semantic search unavailable, falling back to full-text', ['error' => $e->getMessage()]);

            return $fullTextIds;
        }

        return $this->fuse($fullTextIds, $semanticIds)->take($limit)->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function fullTextIds(string $text, int $limit = 50): Collection
    {
        return SearchableTitle::query()
            ->whereRaw("search_vector @@ plainto_tsquery('english', ?)", [$text])
            ->orderByRaw("ts_rank(search_vector, plainto_tsquery('english', ?)) DESC", [$text])
            ->limit($limit)
            ->pluck('title_id');
    }

    /**
     * @return Collection<int, int>
     */
    public function semanticIds(string $text, int $limit = 50): Collection
    {
        $vector = $this->queryVector($text);

        return DB::transaction(function () use ($vector, $limit) {
            DB::statement("SET LOCAL hnsw.iterative_scan = 'strict_order'");

            return Title::query()
                ->join('title_search_embeddings as tse', 'tse.title_id', '=', 'titles.id')
                ->orderByRaw('tse.embedding <=> ?::halfvec', [$vector])
                ->limit($limit)
                ->pluck('titles.id');
        });
    }

    /**
     * @param  Collection<int, int>  ...$rankedLists
     * @return Collection<int, int>
     */
    public function fuse(Collection ...$rankedLists): Collection
    {
        return collect($rankedLists)
            ->flatMap(fn (Collection $ids) => $ids->values()->map(fn (int $id, int $rank) => [
                'id' => $id,
                'score' => 1 / (self::RRF_K + $rank + 1),
            ]))
            ->groupBy('id')
            ->map(fn (Collection $hits) => $hits->sum('score'))
            ->sortDesc()
            ->keys();
    }

    private function queryVector(string $text): string
    {
        $normalized = mb_strtolower(trim($text));

        return Cache::remember(
            'search-embedding:'.TitleSearchEmbedding::DIMENSIONS.':'.sha1($normalized),
            now()->addWeek(),
            fn () => TitleSearchEmbedding::toVectorLiteral(
                $this->embeddingService->embedOne($normalized, TitleSearchEmbedding::DIMENSIONS)
            ),
        );
    }
}
