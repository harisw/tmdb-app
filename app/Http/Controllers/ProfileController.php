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
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', Rule::unique('users')->ignore($request->user()->id)],
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
