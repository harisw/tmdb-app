<?php

namespace App\Http\Controllers\Auth;

use App\Data\DTOs\RegisterUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Services\UserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Register', []);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = $this->userService->create(new RegisterUserData(
            email: $validated['email'],
            password: $validated['password'],
            country_iso2: $validated['country'],
        ));

        event(new Registered($user));

        Auth::login($user);

        return redirect('/');
    }

    public function verifyNotice(): Response
    {
        return Inertia::render('Auth/VerifyEmail');
    }

    public function verifyEmail(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect('/')->with('message', 'Your email has been verified successfully!');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', 'Verification link sent!');
    }
}
