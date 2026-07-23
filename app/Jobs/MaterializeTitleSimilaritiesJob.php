<?php

namespace App\Jobs;

use App\Data\DTOs\TitleSimilarityData;
use App\Models\Title;
use App\Services\KnowledgeGraph\ReasonBuilderService;
use DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MaterializeTitleSimilaritiesJob implements ShouldQueue
{
    use Queueable;

    private const TOP_K = 20;

    public function __construct(public readonly int $titleId) {}

    public function handle(ReasonBuilderService $reasonService): void
    {
        $title = Title::findOrFail($this->titleId);

        if (! $title->embedding) {
            return;
        }

        // Find top-K similar titles using pgvector cosine distance
        // <=> is cosine distance (lower = more similar), so we convert:
        // similarity = 1 - cosine_distance
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
            return;
        }

        // Load both titles for reason generation
        $neighbourIds = array_column($neighbours, 'id');
        $neighbourTitles = Title::with(['genres', 'keywords'])
            ->whereIn('id', $neighbourIds)
            ->get()
            ->keyBy('id');

        $now = now();

        // Each edge expands to a forward and a reverse row (see TitleSimilarityData),
        // so graph queries from either title are cheap without re-materialising the
        // neighbour.
        $upsertData = collect($neighbours)
            ->flatMap(function ($neighbour) use ($title, $neighbourTitles, $reasonService, $now) {
                $neighbourTitle = $neighbourTitles[$neighbour->id] ?? null;
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
