<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendPasswordResetLinkRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use UnexpectedValueException;

class PasswordResetController extends Controller
{
    public function store(SendPasswordResetLinkRequest $request): RedirectResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        return match ($status) {
            Password::ResetLinkSent => back(),
            Password::InvalidUser => back()->withErrors(['email' => 'No Chill account uses this email.']),
            Password::ResetThrottled => back()->withErrors([
                'email' => 'We just sent a link to this email. Give it a minute before asking again.',
            ]),
            default => throw new UnexpectedValueException("Unexpected password reset link status [{$status}]."),
        };
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            return back()->withErrors(['email' => 'This reset link is invalid or has expired. Request a new one.']);
        }

        return redirect()->route('login')->with('message', 'Your password is updated. Sign in with the new one.');
    }
}
