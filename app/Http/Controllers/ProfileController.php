<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $usernameRules = [
            'required',
            'string',
            'min:3',
            'max:30',
            Rule::unique('users')->ignore($request->user()->id),
        ];

        // Reserve the operator's display name against impersonation, including
        // on a fresh database where that row does not exist yet and `unique`
        // would happily hand it out. Admins keep their own name, so this does
        // not lock them out of saving their profile.
        if (! $request->user()->is_admin && ($reserved = config('app.admin_username'))) {
            $usernameRules[] = Rule::notIn([$reserved]);
        }

        $validated = $request->validate([
            'username' => $usernameRules,
            'country' => ['required', 'string', 'exists:countries,iso2'],
            'avatar_style' => ['required', 'string'],
            'hide_adult' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        $user->username = $validated['username'];
        $user->country_id = Country::where('iso2', $validated['country'])->value('id');
        $user->hide_adult = $validated['hide_adult'];
        $user->extra_attributes->set('avatar.style', $validated['avatar_style']);
        $user->extra_attributes->set('avatar.seed', $validated['username']);
        $user->save();

        return redirect()->back();
    }
}
