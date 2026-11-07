<?php

namespace App\Jobs;

use App\Models\Title;
use App\Models\TitleSimilarity;
use App\Services\KnowledgeGraph\ReasonBuilderService;
use DB;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MaterializeTitleSimilaritiesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const TOP_K = 10;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $titleId)
    {
        $this->onQueue('embeddings');
    }

    public function uniqueId(): string
    {
        return (string) $this->titleId;
    }

    public function handle(ReasonBuilderService $reasonService): void
    {
        $title = Title::withoutGlobalScopes()->findOrFail($this->titleId);

        if (! $title->embedding) {
            Log::info("Title {$title->slug} has no embeddings");

            return;
        }

        $minScore = config('features.knowledge_graph.min_score');

        /** @var Collection<int, object{id: int, score: float}> $neighbours */
        $neighbours = collect(DB::select(<<<'SQL'
            SELECT
                t.id,
                1 - (t.embedding <=> :embedding) AS score
            FROM titles t
            WHERE t.id != :id
              AND t.embedding IS NOT NULL
            ORDER BY t.embedding <=> :embedding2
            LIMIT :limit
        SQL, [
            'embedding' => $title->embedding,
            'embedding2' => $title->embedding,
            'id' => $title->id,
            'limit' => self::TOP_K,
        ]))->filter(fn (object $neighbour) => $neighbour->score > $minScore);

        $neighbourTitles = Title::withoutGlobalScopes()
            ->with(['genres', 'keywords', 'topCasts', 'keyCrews'])
            ->findMany($neighbours->pluck('id'))
            ->keyBy('id');

        $now = now();

        $rows = $neighbours
            ->filter(fn (object $neighbour) => $neighbourTitles->has($neighbour->id))
            ->map(fn (object $neighbour) => [
                'title_id' => $title->id,
                'similar_title_id' => $neighbour->id,
                'score' => round($neighbour->score, 6),
                'reasons' => json_encode($reasonService->build($title, $neighbourTitles[$neighbour->id], $neighbour->score)),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($title, $rows, $now) {
            TitleSimilarity::where('title_id', $title->id)->delete();
            TitleSimilarity::insert($rows);
            $title->updateQuietly(['similarities_materialized_at' => $now]);
        });
    }
}
