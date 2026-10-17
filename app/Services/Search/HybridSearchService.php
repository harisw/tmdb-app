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
        $trigramIds = $this->trigramIds($text, $limit);

        try {
            $semanticIds = $this->semanticIds($text, $limit);
        } catch (ConnectionException|RuntimeException $e) {
            Log::warning('Semantic search unavailable, falling back to lexical search', ['error' => $e->getMessage()]);

            return $this->fuse($fullTextIds, $trigramIds)->take($limit)->values();
        }

        return $this->fuse($fullTextIds, $trigramIds, $semanticIds)->take($limit)->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function fullTextIds(string $text, int $limit = 50): Collection
    {
        return $this->fullTextScores($text, $limit)->keys();
    }

    /**
     * @return Collection<int, float>
     */
    public function fullTextScores(string $text, int $limit = 50): Collection
    {
        return SearchableTitle::query()
            ->join('titles', 'titles.id', '=', 'searchable_titles.title_id')
            ->selectRaw(
                "searchable_titles.title_id, ts_rank(searchable_titles.search_vector, plainto_tsquery('english', ?)) * ln(titles.popularity + exp(1)) AS score",
                [$text],
            )
            ->whereRaw("searchable_titles.search_vector @@ plainto_tsquery('english', ?)", [$text])
            ->orderByDesc('score')
            ->limit($limit)
            ->pluck('score', 'title_id')
            ->map(fn (mixed $score) => (float) $score);
    }

    /**
     * @return Collection<int, int>
     */
    public function semanticIds(string $text, int $limit = 50): Collection
    {
        return $this->semanticDistances($text, $limit)->keys();
    }

    /**
     * @return Collection<int, float>
     */
    public function semanticDistances(string $text, int $limit = 50): Collection
    {
        $vector = $this->queryVector($text);

        return DB::transaction(function () use ($vector, $limit) {
            DB::statement("SET LOCAL hnsw.iterative_scan = 'strict_order'");

            return Title::query()
                ->join('title_search_embeddings as tse', 'tse.title_id', '=', 'titles.id')
                ->selectRaw('titles.id, tse.embedding <=> ?::halfvec AS distance', [$vector])
                ->orderBy('distance')
                ->limit($limit)
                ->pluck('distance', 'id')
                ->map(fn (mixed $distance) => (float) $distance);
        });
    }

    /**
     * @return Collection<int, int>
     */
    public function trigramIds(string $text, int $limit = 50): Collection
    {
        return $this->trigramScores($text, $limit)->keys();
    }

    /**
     * @return Collection<int, float>
     */
    public function trigramScores(string $text, int $limit = 50): Collection
    {
        return DB::transaction(function () use ($text, $limit) {
            DB::statement('SET LOCAL pg_trgm.word_similarity_threshold = 0.4');

            return Title::query()
                ->selectRaw('titles.id, word_similarity(?, titles.name) * ln(titles.popularity + exp(1)) AS score', [$text])
                ->whereRaw('? <% titles.name', [$text])
                ->orderByDesc('score')
                ->limit($limit)
                ->pluck('score', 'id')
                ->map(fn (mixed $score) => (float) $score);
        });
    }

    /**
     * @param  Collection<int, int>  ...$rankedLists
     * @return Collection<int, int>
     */
    public function fuse(Collection ...$rankedLists): Collection
    {
        return $this->fusedScores(...$rankedLists)->keys();
    }

    /**
     * @param  Collection<int, int>  ...$rankedLists
     * @return Collection<int, float>
     */
    public function fusedScores(Collection ...$rankedLists): Collection
    {
        return collect($rankedLists)
            ->flatMap(fn (Collection $ids) => $ids->values()->map(fn (int $id, int $rank) => [
                'id' => $id,
                'score' => 1 / (self::RRF_K + $rank + 1),
            ]))
            ->groupBy('id')
            ->map(fn (Collection $hits) => $hits->sum('score'))
            ->sortDesc();
    }

    public function queryVector(string $text): string
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
