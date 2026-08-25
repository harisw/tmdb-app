<?php

namespace App\Http\Controllers;

use App\Data\DTOs\UserTitleEventData;
use App\Http\Requests\PostTitleEventRequest;
use App\Models\Country;
use App\Services\UserTitleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function postTitleEvent(PostTitleEventRequest $req, UserTitleService $service): RedirectResponse
    {
        return $service->createEvent(
            UserTitleEventData::from($req)->setUser($req->user())
        );
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
