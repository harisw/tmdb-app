<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Keyword;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    private const KEYWORD_RESULTS_LIMIT = 12;

    public function edit(Request $req): Response
    {
        $req->validate([
            'tag_query' => ['nullable', 'string', 'max:50'],
        ]);

        return Inertia::render('Settings', [
            'avoidedKeywords' => fn () => $req->user()
                ->avoidedKeywords()
                ->orderByPivot('created_at')
                ->get(['keywords.id', 'keywords.slug', 'keywords.name', 'keywords.count']),
            'maxAvoidedKeywords' => User::MAX_AVOIDED_KEYWORDS,
            'keywordResults' => Inertia::optional(function () use ($req) {
                $query = $req->string('tag_query')->trim()->toString();

                return [
                    'query' => $query,
                    'items' => $query === ''
                        ? []
                        : Keyword::matching($query)->take(self::KEYWORD_RESULTS_LIMIT)->get(['id', 'slug', 'name', 'count']),
                ];
            }),
        ]);
    }

    public function update(Request $req): RedirectResponse
    {
        $usernameRules = [
            'required',
            'string',
            'min:3',
            'max:30',
            Rule::unique('users')->ignore($req->user()->id),
        ];

        if (! $req->user()->is_admin && ($reserved = config('app.admin_username'))) {
            $usernameRules[] = Rule::notIn([$reserved]);
        }

        $validated = $req->validate([
            'username' => $usernameRules,
            'country' => ['required', 'string', 'exists:countries,iso2'],
            'avatar_style' => ['required', 'string'],
            'hide_adult' => ['required', 'boolean'],
        ]);

        $user = $req->user();

        $user->username = $validated['username'];
        $user->country_id = Country::where('iso2', $validated['country'])->value('id');
        $user->hide_adult = $validated['hide_adult'];
        $user->extra_attributes->set('avatar.style', $validated['avatar_style']);
        $user->extra_attributes->set('avatar.seed', $validated['username']);
        $user->save();

        return redirect()->back();
    }

    public function updateCountry(Request $req): RedirectResponse
    {
        $validated = $req->validate([
            'country' => ['required', 'string', 'exists:countries,iso2'],
        ]);

        $user = $req->user();

        $user->update(['country_id' => Country::where('iso2', $validated['country'])->value('id')]);

        return redirect()->back();
    }
}
