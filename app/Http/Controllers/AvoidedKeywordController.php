<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AvoidedKeywordController extends Controller
{
    public function store(Request $req, Keyword $keyword): RedirectResponse
    {
        $avoidedIds = $req->user()->avoidedKeywords()->pluck('keywords.id');

        if (! $avoidedIds->contains($keyword->id) && $avoidedIds->count() >= User::MAX_AVOIDED_KEYWORDS) {
            throw ValidationException::withMessages([
                'keyword' => 'You can avoid up to '.User::MAX_AVOIDED_KEYWORDS.' tags.',
            ]);
        }

        $req->user()->avoidedKeywords()->syncWithoutDetaching([$keyword->id]);

        return redirect()->back();
    }

    public function destroy(Request $req, Keyword $keyword): RedirectResponse
    {
        $req->user()->avoidedKeywords()->detach($keyword->id);

        return redirect()->back();
    }
}
