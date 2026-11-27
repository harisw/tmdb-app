<?php

namespace App\Http\Controllers;

use App\Domain\Enums\TitleSort;
use App\Http\Resources\TitleResource;
use App\Models\Title;
use App\Services\KnowledgeGraph\ConstellationService;
use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request, ConstellationService $constellations, RecommendationService $recommendations): Response
    {
        $seedIndex = $request->validate([
            'seed' => ['nullable', 'integer', 'min:0', 'max:'.(ConstellationService::SEED_POOL - 1)],
        ])['seed'] ?? 0;

        $taste = $request->user()?->taste;
        $personalised = $taste?->taste !== null;

        return Inertia::render('Home', [
            'titles' => TitleResource::collection($personalised
                ? $recommendations->recommendedTitles($taste, self::PAGINATE_SIZE)
                : Title::with(Title::detailRelations())->sorted(TitleSort::POPULAR)->cursorPaginate(self::PAGINATE_SIZE)),
            'personalised' => $personalised,
            'constellation' => Inertia::defer(fn () => $constellations->build((int) $seedIndex)),
        ]);
    }
}
