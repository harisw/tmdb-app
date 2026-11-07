<?php

namespace App\Console\Commands;

use App\Services\Search\HybridSearchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

#[Signature('search:evaluate {mode=fts : fts, semantic, hybrid or hyde} {--golden-set=database/eval/search-golden-set.json}')]
#[Description('Score search modes against the golden set with recall@10 and MRR')]
class SearchEvaluate extends Command
{
    private const CUTOFF = 10;

    private const MODES = ['fts', 'semantic', 'hybrid', 'hyde'];

    public function handle(HybridSearchService $searchService): int
    {
        $mode = $this->argument('mode');

        if (! in_array($mode, self::MODES, true)) {
            $this->error('Mode must be one of: '.implode(', ', self::MODES));

            return self::FAILURE;
        }

        $goldenSet = $this->goldenSet();
        $bar = $this->output->createProgressBar($goldenSet->count());

        $scores = $goldenSet->map(function (array $case) use ($searchService, $mode, $bar) {
            $rankedIds = match ($mode) {
                'fts' => $searchService->lexicalMatches($case['query'], self::CUTOFF)->fullText->keys(),
                'semantic' => $searchService->semanticScores($case['query'], self::CUTOFF)->keys(),
                'hybrid' => $searchService->hybridIds($case['query'], self::CUTOFF),
                'hyde' => $searchService->hydeIds($case['query'], self::CUTOFF),
            };
            $bar->advance();

            return [...$case, ...$this->score($rankedIds->values(), collect($case['relevant']))];
        });
        $bar->finish();
        $this->info("Mode: {$mode}, fusion: {$searchService->fusionMethod()->value}");
        $this->table(
            ['Category', 'Queries', 'Recall@10', 'MRR'],
            $scores->groupBy('category')
                ->map(fn (Collection $group, string $category) => $this->summaryRow($category, $group))
                ->push($this->summaryRow('overall', $scores))
                ->values()
                ->all(),
        );

        $misses = $scores->where('mrr', 0.0);

        if ($misses->isNotEmpty()) {
            $this->warn('No relevant title in the top '.self::CUTOFF.':');
            $misses->each(fn (array $miss) => $this->line("  [{$miss['category']}] {$miss['query']}"));
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{query: string, category: string, relevant: array<int, int>}>
     */
    private function goldenSet(): Collection
    {
        return collect(File::json(base_path($this->option('golden-set')), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  Collection<int, int>  $rankedIds
     * @param  Collection<int, int>  $relevantIds
     * @return array{recall: float, mrr: float}
     */
    private function score(Collection $rankedIds, Collection $relevantIds): array
    {
        $hits = $rankedIds->intersect($relevantIds);
        $firstHitRank = $hits->keys()->first();

        return [
            'recall' => $hits->count() / $relevantIds->count(),
            'mrr' => $firstHitRank === null ? 0.0 : 1 / ($firstHitRank + 1),
        ];
    }

    /**
     * @param  Collection<int, array{query: string, category: string, relevant: array<int, int>, recall: float, mrr: float}>  $scores
     * @return array<int, string|int>
     */
    private function summaryRow(string $category, Collection $scores): array
    {
        return [
            $category,
            $scores->count(),
            number_format($scores->avg('recall'), 3),
            number_format($scores->avg('mrr'), 3),
        ];
    }
}
