<?php

namespace App\Services\Search;

use App\Domain\Enums\FusionMethod;
use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\KnowledgeGraph\OpenAIEmbeddingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use IntlBreakIterator;
use RuntimeException;

class HybridSearchService
{
    public const HYDE_MIN_WORDS = 3;

    private const TRIGRAM_THRESHOLD = 0.4;

    private const CONFIDENT_WORD_SIMILARITY = 0.75;

    public function __construct(
        private readonly OpenAIEmbeddingService $embeddingService,
        private readonly HypotheticalDocumentGenerator $documentGenerator,
    ) {}

    /**
     * @return Collection<int, int>
     */
    public function hybridIds(string $text, int $limit = 50): Collection
    {
        return $this->rankHybrid($text, $this->lexicalMatches($text, $limit), $limit);
    }

    /**
     * @return Collection<int, int>
     */
    public function hydeIds(string $text, int $limit = 50): Collection
    {
        $lexical = $this->lexicalMatches($text, $limit);

        if ($lexical->confident || ! $this->qualifiesForHyde($text)) {
            return $this->rankHybrid($text, $lexical, $limit);
        }

        try {
            return $this->rank([...$lexical->lists(), ...$this->hypothesisLists($text, $limit)], $limit);
        } catch (ConnectionException|RuntimeException $e) {
            Log::warning('HyDE unavailable, falling back to hybrid search', ['error' => $e->getMessage()]);

            return $this->rankHybrid($text, $lexical, $limit);
        }
    }

    public function qualifiesForHyde(string $text): bool
    {
        $boundaries = IntlBreakIterator::createWordInstance();
        $boundaries->setText($text);
        $words = 0;

        while ($boundaries->next() !== IntlBreakIterator::DONE) {
            $words += $boundaries->getRuleStatus() >= IntlBreakIterator::WORD_NONE_LIMIT ? 1 : 0;
        }

        return $words >= self::HYDE_MIN_WORDS;
    }

    public function lexicalMatches(string $text, int $limit = 50): LexicalMatches
    {
        $fullText = $this->fullTextMatches($text, $limit);
        $trigram = $this->trigramMatches($text, $limit);

        return new LexicalMatches(
            fullText: $fullText->mapWithKeys(fn (object $match) => [$match->id => (float) $match->score]),
            trigram: $trigram->mapWithKeys(fn (object $match) => [$match->id => (float) $match->score]),
            confident: (bool) $fullText->first()?->has_name_match
                || $trigram->first()?->best_similarity >= self::CONFIDENT_WORD_SIMILARITY,
        );
    }

    /**
     * @return Collection<int, float>
     */
    public function semanticScores(string $text, int $limit = 50): Collection
    {
        return $this->nearestSimilarities($this->queryVector($text), $limit);
    }

    /**
     * @return Collection<int, float>
     */
    public function hypothesisScores(string $document, int $limit = 50): Collection
    {
        return $this->nearestSimilarities($this->cachedVector($document), $limit);
    }

    /**
     * @param  array<int, Collection<int, float>>  $scoredLists
     * @return Collection<int, float>
     */
    public function fusedScores(array $scoredLists): Collection
    {
        $fusionMethod = $this->fusionMethod();

        return collect($scoredLists)
            ->flatMap(fn (Collection $scores) => $fusionMethod->normalize($scores)
                ->map(fn (float $score, int $titleId) => ['id' => $titleId, 'score' => $score])
                ->values())
            ->groupBy('id')
            ->map(fn (Collection $hits) => $hits->sum('score'))
            ->sortDesc();
    }

    public function fusionMethod(): FusionMethod
    {
        return FusionMethod::from(config('search.fusion'));
    }

    public function queryVector(string $text): string
    {
        return $this->cachedVector(mb_strtolower(trim($text)));
    }

    /**
     * @param  array<int, Collection<int, float>>  $scoredLists
     * @return Collection<int, int>
     */
    private function rank(array $scoredLists, int $limit): Collection
    {
        return $this->fusedScores($scoredLists)->keys()->take($limit)->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function rankHybrid(string $text, LexicalMatches $lexical, int $limit): Collection
    {
        try {
            return $this->rank([...$lexical->lists(), $this->semanticScores($text, $limit)], $limit);
        } catch (ConnectionException|RuntimeException $e) {
            Log::warning('Semantic search unavailable, falling back to lexical search', ['error' => $e->getMessage()]);

            return $this->rank($lexical->lists(), $limit);
        }
    }

    /**
     * @return array<int, Collection<int, float>>
     */
    private function hypothesisLists(string $text, int $limit): array
    {
        $hypothesis = $this->documentGenerator->generate($text);

        return [
            $this->hypothesisScores($hypothesis->document, $limit),
            ...($hypothesis->title === null ? [] : $this->lexicalMatches($hypothesis->title, $limit)->lists()),
        ];
    }

    /**
     * @return Collection<int, object{id: int, score: float, has_name_match: bool}>
     */
    private function fullTextMatches(string $text, int $limit): Collection
    {
        return Title::query()
            ->join('searchable_titles', 'searchable_titles.title_id', '=', 'titles.id')
            ->selectRaw(
                "titles.id,
                ts_rank(searchable_titles.search_vector, plainto_tsquery('english', ?)) * ln(titles.popularity + exp(1)) AS score,
                bool_or(ts_filter(searchable_titles.search_vector, '{a}') @@ plainto_tsquery('english', ?)) OVER () AS has_name_match",
                [$text, $text],
            )
            ->whereRaw("searchable_titles.search_vector @@ plainto_tsquery('english', ?)", [$text])
            ->orderByDesc('score')
            ->limit($limit)
            ->toBase()
            ->get();
    }

    /**
     * @return Collection<int, object{id: int, score: float, best_similarity: float}>
     */
    private function trigramMatches(string $text, int $limit): Collection
    {
        return DB::transaction(function () use ($text, $limit) {
            DB::statement('SET LOCAL pg_trgm.word_similarity_threshold = '.self::TRIGRAM_THRESHOLD);

            return Title::query()
                ->selectRaw(
                    'titles.id,
                    word_similarity(?, titles.name) * ln(titles.popularity + exp(1)) AS score,
                    max(word_similarity(?, titles.name)) OVER () AS best_similarity',
                    [$text, $text],
                )
                ->whereRaw('? <% titles.name', [$text])
                ->orderByDesc('score')
                ->limit($limit)
                ->toBase()
                ->get();
        });
    }

    /**
     * @return Collection<int, float>
     */
    private function nearestSimilarities(string $vector, int $limit): Collection
    {
        return DB::transaction(function () use ($vector, $limit) {
            DB::statement("SET LOCAL hnsw.iterative_scan = 'strict_order'");

            return Title::query()
                ->join('title_search_embeddings as tse', 'tse.title_id', '=', 'titles.id')
                ->selectRaw('titles.id, tse.embedding <=> ?::halfvec AS distance', [$vector])
                ->orderBy('distance')
                ->limit($limit)
                ->pluck('distance', 'id')
                ->map(fn (mixed $distance) => 1 - (float) $distance);
        });
    }

    private function cachedVector(string $text): string
    {
        return Cache::remember(
            'search-embedding:'.TitleSearchEmbedding::DIMENSIONS.':'.sha1($text),
            now()->addWeek(),
            fn () => TitleSearchEmbedding::toVectorLiteral(
                $this->embeddingService->embedOne($text, TitleSearchEmbedding::DIMENSIONS)
            ),
        );
    }
}
