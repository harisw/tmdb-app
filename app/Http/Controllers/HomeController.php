<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
use App\Models\Title;
use App\Services\KnowledgeGraph\ConstellationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request, ConstellationService $constellations): Response
    {
        $seedIndex = $request->validate([
            'seed' => ['nullable', 'integer', 'min:0', 'max:'.(ConstellationService::SEED_POOL - 1)],
        ])['seed'] ?? 0;

        return Inertia::render('Home', [
            'titles' => TitleResource::collection(Title::listing()
                ->paginate(self::PAGINATE_SIZE)),
            'constellation' => Inertia::defer(fn () => $constellations->build((int) $seedIndex)),
        ]);
    }
}
