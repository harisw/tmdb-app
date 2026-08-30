<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
use App\Models\Title;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const PAGINATE_SIZE = 30;

    public function index(): Response
    {
        $titles = Title::with(Title::detailRelations())
            ->latest()
            ->paginate(self::PAGINATE_SIZE);

        return Inertia::render('Home', [
            'titles' => TitleResource::collection($titles),
        ]);
    }
}
