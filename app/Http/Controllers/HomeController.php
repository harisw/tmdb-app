<?php

namespace App\Http\Controllers;

use App\Http\Resources\TitleResource;
use App\Models\Title;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Home', [
            'titles' => TitleResource::collection(Title::listing()
                ->paginate(self::PAGINATE_SIZE)),
        ]);
    }
}
