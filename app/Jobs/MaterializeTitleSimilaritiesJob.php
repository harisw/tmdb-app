<?php

namespace App\Jobs;

use App\Data\DTOs\TitleSimilarityData;
use App\Models\Title;
use App\Services\KnowledgeGraph\ReasonBuilderService;
use DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MaterializeTitleSimilaritiesJob implements ShouldQueue
{
    use Queueable;

    private const TOP_K = 10;

    public function __construct(public readonly int $titleId)
    {
        $this->onQueue('embeddings');
    }

    public function handle(ReasonBuilderService $reasonService): void
    {
        $title = Title::findOrFail($this->titleId);

        if (!$title->embedding) {
            Log::info("Title {$title->slug} has no embeddings");

            return;
        }

        $neighbours = DB::select(<<<'SQL'
            SELECT
                t.id,
                t.name,
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
        ]);

        if (empty($neighbours)) {
            Log::info("Title {$title->slug} has no neighbours");

            return;
        }

        // Load both titles for reason generation
        $neighbourIds = array_column($neighbours, 'id');
        $neighbourTitles = Title::with(['genres', 'keywords', 'topCasts', 'keyCrews'])
            ->whereIn('id', $neighbourIds)
            ->get()
            ->keyBy('id');

        $now = now();

        // Each edge expands to a forward and a reverse row (see TitleSimilarityData),
        // so graph queries from either title are cheap without re-materialising the
        // neighbour.

        $minScore = config('features.knowledge_graph.min_score');

        $upsertData = collect($neighbours)
            ->filter(fn($nb) => $nb->score > $minScore)
            ->flatMap(function ($neighbour) use ($title, $neighbourTitles, $reasonService, $now) {
                $neighbourTitle = $neighbourTitles[$neighbour->id] ?? null;

                Log::info("Title {$title->slug} is neighbouring with {$neighbourTitle->slug}| score {$neighbour->score}");
                $reasons = $neighbourTitle
                    ? $reasonService->build($title, $neighbourTitle, $neighbour->score)
                    : [];

                return (new TitleSimilarityData(
                    titleId: $title->id,
                    similarTitleId: $neighbour->id,
                    score: $neighbour->score,
                    reasons: $reasons,
                ))->toUpsertRows($now);
            })
            ->all();

        DB::table('title_similarities')->upsert(
            $upsertData,
            ['title_id', 'similar_title_id'],
            ['score', 'reasons', 'updated_at'],
        );
    }
}
