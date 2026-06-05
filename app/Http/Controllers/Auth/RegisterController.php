<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\User;
use App\Utils\Helpers\GenerateUsername;
use App\Utils\Traits\HasAuthMethods;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    use HasAuthMethods;

    public function create(): Response
    {
        return Inertia::render('Auth/Register', []);
    }

    public function store(Request $request, GenerateUsername $generateUsername): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'country' => ['required', 'string', 'max:3', 'exists:countries,iso2'],
        ]);

        $user = User::create([
            'name' => explode('@', $validated['email'])[0],
            'username' => $this->getUniqueUsername($generateUsername),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'country_id' => Country::where('iso2', $validated['country'])->first()->id,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect('/')->with('message', 'Please check your email to verify your account.');
    }

    public function verifyNotice(): Response
    {
        return Inertia::render('Auth/VerifyEmail');
    }

    public function verifyEmail(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect('/')->with('verification-message', 'Verification is successful!');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', 'Verification link sent!');
    }
}
