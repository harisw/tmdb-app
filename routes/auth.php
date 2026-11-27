<?php

// Email verification
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AvoidedKeywordController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;

Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:6,1');
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);

Route::middleware('guest')->group(function () {
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:6,1')->name('password.update');
});

Route::get('/email/verify', [RegisterController::class, 'verifyNotice'])
    ->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', [RegisterController::class, 'verifyEmail'])
    ->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', [RegisterController::class, 'resendVerification'])
    ->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// Google OAuth
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

// Logout (already exists but add if missing)
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/settings', [ProfileController::class, 'edit'])->name('settings');
    Route::post('/settings/avoided-tags/{keyword}', [AvoidedKeywordController::class, 'store'])
        ->name('avoided-tags.store');
    Route::delete('/settings/avoided-tags/{keyword}', [AvoidedKeywordController::class, 'destroy'])
        ->name('avoided-tags.destroy');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/country', [ProfileController::class, 'updateCountry'])
        ->name('profile.update-country');

    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::post('/user/title/event', [UserController::class, 'postTitleEvent'])->name('user.post-title-event');

    Route::get('/user/titles', [UserController::class, 'titleLists'])->name('user.title-lists');
    Route::get('/user/titles/{type}', [UserController::class, 'showTitleList'])->name('user.show-title-list');
});
