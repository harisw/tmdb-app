<?php

namespace App\Http\Controllers;

use App\Models\Title;
use Illuminate\Http\JsonResponse;

class TitleController extends Controller
{
    public function show(Title $title): JsonResponse
    {
        $title->load(Title::detailRelations());

        return response()->json([
            'title' => $title,
        ]);
    }
}
