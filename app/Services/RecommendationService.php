<?php

namespace App\Services;

use App\Domain\Enums\TitleSort;
use App\Models\Genre;
use App\Models\Scopes\TitleTypeScope;
use App\Models\Title;
use App\Models\UserTaste;
use App\Models\UserTitleState;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    public const ONBOARDING_ROUNDS = 8;

    private const ONBOARDING_TITLES_PER_GENRE = 1;

    /**
     * @return Collection<int, Title>
     */
    public function onboardingTitles(int $round = 0): Collection
    {
        return Genre::query()
            ->where('count', '>', 0)
            ->with(['titles' => /** @param BelongsToMany<Title, Genre> $titles */ fn (BelongsToMany $titles) => $titles
                ->withoutGlobalScope(TitleTypeScope::class)
                ->whereNotNull('titles.embedding')
                ->sorted(TitleSort::POPULAR)
                ->limit(self::ONBOARDING_TITLES_PER_GENRE)
                ->offset($round * self::ONBOARDING_TITLES_PER_GENRE),
            ])
            ->orderByDesc('count')
            ->get()
            ->flatMap->titles
            ->unique('id')
            ->values();
    }

    /**
     * @return Paginator<int, Title>
     */
    public function recommendedTitles(UserTaste $taste, int $perPage): Paginator
    {
        return DB::transaction(function () use ($taste, $perPage): Paginator {
            DB::statement("SET LOCAL hnsw.iterative_scan = 'strict_order'");

            return Title::with(Title::detailRelations())
                ->whereNotNull('titles.embedding')
                ->whereNotIn('titles.id', UserTitleState::select('title_id')->where('user_id', $taste->user_id))
                ->orderByRaw('titles.embedding <=> ?::vector', [$taste->taste])
                ->simplePaginate($perPage);
        });
    }
}
