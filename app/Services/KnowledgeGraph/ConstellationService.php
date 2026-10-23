<?php

namespace App\Services\KnowledgeGraph;

use App\Models\Title;
use App\Models\TitleSimilarity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ConstellationService
{
    public const SEED_POOL = 5;

    public const MAX_NODES = 24;

    private const MIN_NEIGHBOURS = 4;

    private const NEIGHBOURS = 8;

    private const SECOND_HOP = 2;

    private const NODE_COLUMNS = ['titles.id', 'slug', 'name', 'poster_path', 'release_date', 'rating', 'type'];

    /**
     * @return array{seed: int, seedIndex: int, seedCount: int, nodes: list<array<string, mixed>>, links: list<array<string, mixed>>}|null
     */
    public function build(int $seedIndex = 0): ?array
    {
        return Cache::remember($this->cacheKey($seedIndex), now()->addHour(), fn () => $this->compute($seedIndex));
    }

    /**
     * @return array{seed: int, seedIndex: int, seedCount: int, nodes: list<array<string, mixed>>, links: list<array<string, mixed>>}|null
     */
    /**
     * @return Collection<int, Title>
     */
    public function seeds(): Collection
    {
        return Title::query()
            ->whereHas(
                'similarTitles',
                fn (Builder $query) => $query->where('title_similarities.score', '>=', config('features.knowledge_graph.min_score')),
                '>=',
                self::MIN_NEIGHBOURS,
            )
            ->orderByDesc('popularity')
            ->limit(self::SEED_POOL)
            ->get(self::NODE_COLUMNS);
    }

    private function compute(int $seedIndex): ?array
    {
        $minScore = config('features.knowledge_graph.min_score');
        $seeds = $this->seeds();

        if ($seeds->isEmpty()) {
            return null;
        }

        $seedIndex %= $seeds->count();
        $seed = $seeds[$seedIndex];

        $neighbours = $seed->similarTitles()
            ->wherePivot('score', '>=', $minScore)
            ->limit(self::NEIGHBOURS)
            ->get(self::NODE_COLUMNS);

        $hops = collect([$seed->id => 0])->union($neighbours->mapWithKeys(fn (Title $title) => [$title->id => 1]));
        $nodes = collect([$seed])->merge($neighbours)->keyBy('id');
        $links = collect();

        foreach ($neighbours as $neighbour) {
            $this->link($links, $seed->id, $neighbour->id, $neighbour->pivot->score, $neighbour->pivot->reasons);
        }

        $secondHop = TitleSimilarity::query()
            ->whereIn('title_id', $neighbours->modelKeys())
            ->where('score', '>=', $minScore)
            ->orderByDesc('score')
            ->get();

        $candidates = Title::query()
            ->whereIn('titles.id', $secondHop->pluck('similar_title_id')->unique())
            ->get(self::NODE_COLUMNS)
            ->keyBy('id');

        $addedPerParent = [];

        foreach ($secondHop as $edge) {
            $targetId = $edge->similar_title_id;

            if (! $nodes->has($targetId)) {
                if (
                    ! $candidates->has($targetId)
                    || $nodes->count() >= self::MAX_NODES
                    || ($addedPerParent[$edge->title_id] ?? 0) >= self::SECOND_HOP
                ) {
                    continue;
                }

                $nodes->put($targetId, $candidates[$targetId]);
                $hops->put($targetId, 2);
                $addedPerParent[$edge->title_id] = ($addedPerParent[$edge->title_id] ?? 0) + 1;
            }

            $this->link($links, $edge->title_id, $targetId, $edge->score, $edge->reasons);
        }

        return [
            'seed' => $seed->id,
            'seedIndex' => $seedIndex,
            'seedCount' => $seeds->count(),
            'nodes' => $nodes->map(fn (Title $title) => [...$title->toGraphNode(), 'hop' => $hops[$title->id]])
                ->values()
                ->all(),
            'links' => $links->values()->all(),
        ];
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $links
     */
    private function link(Collection $links, int $sourceId, int $targetId, float $score, array $reasons): void
    {
        $key = min($sourceId, $targetId).'-'.max($sourceId, $targetId);

        if ($links->has($key)) {
            return;
        }

        $links->put($key, [
            'source' => $sourceId,
            'target' => $targetId,
            'score' => round($score, 3),
            'reasons' => $reasons,
        ]);
    }

    private function cacheKey(int $seedIndex): string
    {
        $user = auth()->user();

        return implode(':', [
            'constellation',
            request('title_type') ?: 'all',
            md5(implode(',', $user?->avoidedKeywordIds() ?? [])),
            $seedIndex,
        ]);
    }
}
