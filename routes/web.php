<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Appearance;

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
});

// Add home route for authenticated users
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        // Redirect authenticated users to the Filament admin dashboard by default
        // Filament registers the dashboard page as 'my-dashboard' by default
        return redirect()->route('filament.admin.pages.my-dashboard');
    })->name('home');
});

// Provide a small compatibility route named 'login' so framework helpers
// and third-party packages that call route('login') can redirect guests
// to the Filament admin sign-in page. This route simply redirects to
// the Filament admin base path which serves the login UI.
Route::get('/login', function () {
    return redirect('/admin');
})->name('login');

// Provide a logout route for testing compatibility
Route::post('/logout', function () {
    Auth::logout();
    return redirect('/admin');
})->name('logout');

// Local-only route to serve temporary exported files (used by Filament/Livewire JSON flow)
Route::get('exports/download/{filename}', function ($filename) {
    if (app()->environment() !== 'local') {
        abort(404);
    }

    $path = storage_path('app/exports/' . basename($filename));
    if (! file_exists($path)) {
        abort(404);
    }

    return response()->download($path, $filename)->deleteFileAfterSend(true);
})->name('exports.download');


