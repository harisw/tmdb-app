<?php

namespace App\Console\Commands;

use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\KnowledgeGraph\OpenAIEmbeddingService;
use App\Services\Search\HybridSearchService;
use App\Services\Search\HypotheticalDocument;
use App\Services\Search\HypotheticalDocumentGenerator;
use App\Services\Search\LexicalMatches;
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

        $lexical = $this->searchService->lexicalMatches($query, self::WINDOW);
        $semantic = $this->searchService->semanticScores($query, self::WINDOW);
        $hybrid = $this->searchService->fusedScores([...$lexical->lists(), $semantic]);
        $hydeSkipReason = match (true) {
            ! $this->searchService->qualifiesForHyde($query) => 'query has fewer than '.HybridSearchService::HYDE_MIN_WORDS.' words',
            $lexical->confident => 'a title name already matches the query closely',
            default => null,
        };
        $hypothesis = $hydeSkipReason === null ? $this->documentGenerator->generate($query) : null;
        $hyde = $hypothesis === null ? collect() : $this->searchService->hypothesisScores($hypothesis->document, self::WINDOW);
        $titleGuess = $hypothesis?->title === null ? null : $this->searchService->lexicalMatches($hypothesis->title, self::WINDOW);
        $titleFullText = $titleGuess->fullText ?? collect();
        $titleTrigram = $titleGuess->trigram ?? collect();
        $hybridWithHyde = $this->searchService->fusedScores([...$lexical->lists(), $hyde, $titleFullText, $titleTrigram]);
        $fusion = $this->searchService->fusionMethod()->value;

        $titles = Title::withoutGlobalScopes()
            ->whereIn('id', collect([...$lexical->lists(), $semantic, $hyde, $titleFullText, $titleTrigram])->flatMap->keys()->merge($expectedIds)->unique())
            ->get(['id', 'name', 'release_date'])
            ->keyBy('id');

        $this->renderRanking('Full-text (ts_rank × popularity, higher is better)', $lexical->fullText, $titles, $expectedIds);
        $this->renderRanking('Trigram (word similarity × popularity, higher is better)', $lexical->trigram, $titles, $expectedIds);
        $this->renderRanking('Semantic (cosine similarity, higher is better)', $semantic, $titles, $expectedIds);
        $this->renderRanking("Hybrid ({$fusion} score, higher is better)", $hybrid, $titles, $expectedIds);
        $this->renderHyde($hypothesis, $hydeSkipReason, $fusion, $hyde, $titleGuess, $hybridWithHyde, $titles, $expectedIds);

        $expectedIds->each(fn (int $titleId) => $this->explainExpected(
            $titleId,
            $this->searchService->queryVector($query),
            [
                'Full-text' => $lexical->fullText,
                'Trigram' => $lexical->trigram,
                'Semantic' => $semantic,
                'Hybrid' => $hybrid,
                ...($hypothesis === null ? [] : [
                    'HyDE' => $hyde,
                    'Title text' => $titleFullText,
                    'Title trgm' => $titleTrigram,
                    'HyDE fused' => $hybridWithHyde,
                ]),
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
    private function renderHyde(
        ?HypotheticalDocument $hypothesis,
        ?string $skipReason,
        string $fusion,
        Collection $hyde,
        ?LexicalMatches $titleGuess,
        Collection $hybridWithHyde,
        Collection $titles,
        Collection $expectedIds,
    ): void {
        $this->newLine();

        if ($hypothesis === null) {
            $this->info('HyDE');
            $this->line("  skipped: {$skipReason}, so hydeIds falls back to hybrid");

            return;
        }

        $this->info('Hypothetical document (LLM output, document embedded as-is)');
        $this->line('    Title guess: '.($hypothesis->title ?? 'none'));
        $this->line(collect(explode("\n", $hypothesis->document))->map(fn (string $line) => "    {$line}")->join("\n"));

        $this->renderRanking('HyDE (cosine similarity to the hypothetical document, higher is better)', $hyde, $titles, $expectedIds);

        if ($titleGuess !== null) {
            $this->renderRanking('Full-text on the title guess (ts_rank × popularity, higher is better)', $titleGuess->fullText, $titles, $expectedIds);
            $this->renderRanking('Trigram on the title guess (word similarity × popularity, higher is better)', $titleGuess->trigram, $titles, $expectedIds);
        }

        $this->renderRanking("Hybrid + HyDE ({$fusion} score over full-text, trigram, HyDE and the title guess, higher is better)", $hybridWithHyde, $titles, $expectedIds);
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
        $lineSimilarities = collect($this->embeddingService->embedBatch($documentLines->all(), TitleSearchEmbedding::DIMENSIONS))
            ->map(fn (array $lineVector) => (float) DB::scalar(
                'SELECT 1 - (?::halfvec <=> ?::halfvec)',
                [$queryVector, TitleSearchEmbedding::toVectorLiteral($lineVector)],
            ));
        $closestLine = $lineSimilarities->sortDesc()->keys()->first();

        $this->line('  Each document line embedded on its own (cosine similarity to the query, higher is better):');
        $documentLines->each(fn (string $line, int $i) => $this->line(sprintf(
            '    %.4f  %s%s',
            $lineSimilarities[$i],
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

        $this->line(sprintf('  Cosine similarity %.4f, exact rank %d among all embedded titles', 1 - $distance, $exactRank));
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
