<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Utils\Helpers\GenerateUsername;
use App\Utils\Traits\HasAuthMethods;
use Auth;
use Laravel\Socialite\Socialite;

class GoogleAuthController extends Controller
{
    use HasAuthMethods;

    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(GenerateUsername $generateUsername)
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::where('google_id', $googleUser->getId())->first();
        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'google_token' => $googleUser->token,
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'username' => $this->getUniqueUsername($generateUsername),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'google_token' => $googleUser->token,
                    'email_verified_at' => now(), // Google already verified the email
                ]);
            }
        }
        Auth::login($user, true);

        return redirect('/');
    }
}
