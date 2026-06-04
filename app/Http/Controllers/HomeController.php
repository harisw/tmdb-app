<?php

namespace App\Http\Controllers;

use App\Models\Title;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const PAGINATE_SIZE = 30;

    public function index(): Response
    {
        $titles = Title::with(['genres', 'keywords', 'titleProviders' => function ($q) {
            $q->with('provider')->uniqueByProvider();
        }])->latest()->paginate(self::PAGINATE_SIZE);

        return Inertia::render('Home', [
            'titles' => $titles,
        ]);
    }
}
