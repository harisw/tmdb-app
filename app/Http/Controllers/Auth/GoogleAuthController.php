<?php

namespace App\Http\Controllers\Auth;

use App\Data\DTOs\GoogleUserData;
use App\Http\Controllers\Controller;
use App\Services\UserService;
use Auth;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User;

class GoogleAuthController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        /** @var User $googleUser */
        $googleUser = Socialite::driver('google')->user();

        $data = new GoogleUserData(
            name: $googleUser->getName(),
            email: $googleUser->getEmail(),
            google_id: $googleUser->getId(),
            google_token: $googleUser->token,
        );

        $user = $this->userService->findByGoogleId($data->google_id);

        if (! $user) {
            $user = $this->userService->findByEmail($data->email);
            $user = $user
                ? $this->userService->linkGoogleAccount($user, $data)
                : $this->userService->createFromGoogle($data);
        }

        Auth::login($user, true);

        return redirect('/');
    }
}
