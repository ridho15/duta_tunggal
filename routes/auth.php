<?php

use App\Livewire\Auth\ConfirmPassword;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use Illuminate\Support\Facades\Route;

// Authentication routing is handled by the Filament admin panel mounted at
// /admin. The application deliberately removed custom /register, /login,
// /forgot-password and /reset-password routes so that Filament's native
// auth UI is the canonical source of truth for admin authentication.

// If you need to expose public (non-admin) auth routes in future, re-add
// them here. For now, leave auth entirely to Filament's panel provider.

// Additional auth routes for testing and public access
Route::middleware('guest')->group(function () {
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('/verify-email', VerifyEmail::class)->name('verification.notice');
    Route::get('/confirm-password', ConfirmPassword::class)->name('password.confirm');
});

// Email verification routes
Route::middleware(['auth', 'throttle:6,1'])->group(function () {
    Route::get('/email/verify/{id}/{hash}', function (\Illuminate\Http\Request $request) {
        if (! hash_equals((string) $request->route('id'), (string) $request->user()->getKey())) {
            throw new \Illuminate\Auth\Access\AuthorizationException;
        }

        if (! hash_equals((string) $request->route('hash'), sha1($request->user()->getEmailForVerification()))) {
            throw new \Illuminate\Auth\Access\AuthorizationException;
        }

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->markEmailAsVerified();

        // Manually dispatch the Verified event to ensure it's captured by tests
        \Illuminate\Support\Facades\Event::dispatch(new \Illuminate\Auth\Events\Verified($request->user()));

        return redirect()->route('dashboard', ['verified' => 1]);
    })->middleware('signed')->name('verification.verify');

    Route::post('/email/verification-notification', function (\Illuminate\Http\Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    })->name('verification.send');
});

