<?php

namespace App\Console\Commands;

use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\KnowledgeGraph\OpenAIEmbeddingService;
use App\Services\Search\HybridSearchService;
use App\Services\Search\HypotheticalDocumentGenerator;
use App\Services\Search\SearchDocumentBuilder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('search:explain {query} {--expect=* : Title IDs you expect the query to find} {--limit=10 : Rows shown per retriever} {--lines : Embed each document line to show which parts match the query}')]
#[Description('Show how each retriever ranks a query and why an expected title does or does not appear')]
class SearchExplain extends Command
{
    private const WINDOW = 50;

    public function __construct(
        private readonly HybridSearchService $searchService,
        private readonly SearchDocumentBuilder $documentBuilder,
        private readonly OpenAIEmbeddingService $embeddingService,
        private readonly HypotheticalDocumentGenerator $documentGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = $this->argument('query');
        $expectedIds = collect($this->option('expect'))->map(fn (string $id) => (int) $id);

        $fullText = $this->searchService->fullTextScores($query, self::WINDOW);
        $trigram = $this->searchService->trigramScores($query, self::WINDOW);
        $semantic = $this->searchService->semanticDistances($query, self::WINDOW);
        $hybrid = $this->searchService->fusedScores($fullText->keys(), $trigram->keys(), $semantic->keys());
        $hydeDocument = $this->searchService->qualifiesForHyde($query) ? $this->documentGenerator->generate($query) : null;
        $hyde = $hydeDocument === null ? collect() : $this->searchService->hypotheticalDocumentDistances($hydeDocument, self::WINDOW);
        $hybridWithHyde = $this->searchService->fusedScores($fullText->keys(), $trigram->keys(), $semantic->keys(), $hyde->keys());

        $titles = Title::withoutGlobalScopes()
            ->whereIn('id', $fullText->keys()->merge($trigram->keys())->merge($semantic->keys())->merge($hyde->keys())->merge($expectedIds)->unique())
            ->get(['id', 'name', 'release_date', 'is_adult'])
            ->keyBy('id');

        $this->renderRanking('Full-text (ts_rank × popularity, higher is better)', $fullText, $titles, $expectedIds);
        $this->renderRanking('Trigram (word similarity × popularity, higher is better)', $trigram, $titles, $expectedIds);
        $this->renderRanking('Semantic (cosine distance, lower is better)', $semantic, $titles, $expectedIds);
        $this->renderRanking('Hybrid (RRF score, higher is better)', $hybrid, $titles, $expectedIds);
        $this->renderHyde($hydeDocument, $hyde, $hybridWithHyde, $titles, $expectedIds);

        $expectedIds->each(fn (int $titleId) => $this->explainExpected(
            $titleId,
            $this->searchService->queryVector($query),
            [
                'Full-text' => $fullText,
                'Trigram' => $trigram,
                'Semantic' => $semantic,
                'Hybrid' => $hybrid,
                ...($hydeDocument === null ? [] : ['HyDE' => $hyde, 'HyDE fused' => $hybridWithHyde]),
            ],
        ));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, float>  $scores
     * @param  Collection<int, Title>  $titles
     * @param  Collection<int, int>  $expectedIds
     */
    private function renderRanking(string $heading, Collection $scores, Collection $titles, Collection $expectedIds): void
    {
        $this->newLine();
        $this->info($heading);

        if ($scores->isEmpty()) {
            $this->line('  no results');

            return;
        }

        $this->table(
            ['#', 'ID', 'Title', 'Score'],
            $scores->take((int) $this->option('limit'))
                ->map(fn (float $score, int $titleId) => [
                    $titleId,
                    $this->label($titles->get($titleId)).($expectedIds->contains($titleId) ? '  ◀ expected' : ''),
                    number_format($score, 4),
                ])
                ->values()
                ->map(fn (array $row, int $rank) => [$rank + 1, ...$row])
                ->all(),
        );
    }

    /**
     * @param  Collection<int, float>  $hyde
     * @param  Collection<int, float>  $hybridWithHyde
     * @param  Collection<int, Title>  $titles
     * @param  Collection<int, int>  $expectedIds
     */
    private function renderHyde(?string $hydeDocument, Collection $hyde, Collection $hybridWithHyde, Collection $titles, Collection $expectedIds): void
    {
        $this->newLine();

        if ($hydeDocument === null) {
            $this->info('HyDE');
            $this->line('  skipped: query has fewer than '.HybridSearchService::HYDE_MIN_WORDS.' words, so hydeIds falls back to hybrid');

            return;
        }

        $this->info('Hypothetical document (LLM output, embedded as-is)');
        $this->line(collect(explode("\n", $hydeDocument))->map(fn (string $line) => "    {$line}")->join("\n"));

        $this->renderRanking('HyDE (cosine distance to the hypothetical document, lower is better)', $hyde, $titles, $expectedIds);
        $this->renderRanking('Hybrid + HyDE (RRF score over all four lists, higher is better)', $hybridWithHyde, $titles, $expectedIds);
    }

    /**
     * @param  array<string, Collection<int, float>>  $rankings
     */
    private function explainExpected(int $titleId, string $queryVector, array $rankings): void
    {
        $this->newLine();
        $title = Title::withoutGlobalScopes()->with(SearchDocumentBuilder::relations())->find($titleId);

        if (! $title) {
            $this->error("Expected title #{$titleId} does not exist.");

            return;
        }

        $this->info("Expected: #{$titleId} {$this->label($title)}");

        foreach ($rankings as $retriever => $scores) {
            $position = $scores->keys()->search($titleId);
            $this->line(sprintf('  %-10s %s', $retriever, $position === false ? 'not in top '.self::WINDOW : 'rank '.($position + 1)));
        }

        if ($title->is_adult) {
            $this->warn('  Adult title: hidden from guests and users with hide_adult by AdultContentScope.');
        }

        $this->explainEmbedding($titleId, $queryVector, $rankings['Semantic']);

        $documentLines = collect(explode("\n", $this->documentBuilder->build($title)));

        if ($this->option('lines')) {
            $this->explainLines($documentLines, $queryVector);

            return;
        }

        $this->line('  Document the embedding is built from (current builder):');
        $this->line($documentLines->map(fn (string $line) => "    {$line}")->join("\n"));
    }

    /**
     * @param  Collection<int, string>  $documentLines
     */
    private function explainLines(Collection $documentLines, string $queryVector): void
    {
        $lineDistances = collect($this->embeddingService->embedBatch($documentLines->all(), TitleSearchEmbedding::DIMENSIONS))
            ->map(fn (array $lineVector) => (float) DB::scalar(
                'SELECT ?::halfvec <=> ?::halfvec',
                [$queryVector, TitleSearchEmbedding::toVectorLiteral($lineVector)],
            ));
        $closestLine = $lineDistances->sort()->keys()->first();

        $this->line('  Each document line embedded on its own (cosine distance to the query, lower is better):');
        $documentLines->each(fn (string $line, int $i) => $this->line(sprintf(
            '    %.4f  %s%s',
            $lineDistances[$i],
            mb_strimwidth($line, 0, 100, '…'),
            $i === $closestLine ? '  ◀ closest' : '',
        )));
    }

    /**
     * @param  Collection<int, float>  $semantic
     */
    private function explainEmbedding(int $titleId, string $queryVector, Collection $semantic): void
    {
        $embedding = TitleSearchEmbedding::find($titleId);

        if (! $embedding) {
            $this->warn('  No search embedding yet, so semantic search cannot find it.');

            return;
        }

        if ($embedding->document_version !== SearchDocumentBuilder::VERSION) {
            $this->warn("  Embedding is stale: built from document v{$embedding->document_version}, current is v".SearchDocumentBuilder::VERSION.'.');
        }

        $distance = (float) DB::scalar(
            'SELECT embedding <=> ?::halfvec FROM title_search_embeddings WHERE title_id = ?',
            [$queryVector, $titleId],
        );
        $exactRank = 1 + (int) DB::scalar(
            'SELECT count(*) FROM title_search_embeddings WHERE embedding <=> ?::halfvec < ?',
            [$queryVector, $distance],
        );

        $this->line(sprintf('  Cosine distance %.4f, exact rank %d among all embedded titles', $distance, $exactRank));
        $this->line(match (true) {
            $exactRank > self::WINDOW => '  → The model places it far from the query; compare the document with the query wording.',
            $semantic->has($titleId) => '  → Retrieved, but closer titles outrank it; see which document lines pull it away.',
            default => '  → Close enough to be found, yet missing: a scope filter or the index is hiding it.',
        });
    }

    private function label(?Title $title): string
    {
        return $title ? $title->name.($title->release_date ? " ({$title->release_date->format('Y')})" : '') : 'unknown';
    }
}
