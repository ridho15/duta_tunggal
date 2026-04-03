<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Appearance;
use App\Http\Controllers\Reports\StockReportController;
use App\Http\Controllers\Reports\FinancialReportPreviewController;
use App\Http\Controllers\InventoryCardController;

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
        // Redirect authenticated users to the Filament admin dashboard by default.
        // Use the dashboard page class to derive the current route name so
        // production changes (slug overrides) won't break the redirect.
        return redirect()->route(
            \App\Filament\Pages\MyDashboard::getRouteName()
        );
    })->name('home');
});

// Provide a small compatibility route named 'login' so framework helpers
// and third-party packages that call route('login') can redirect guests
// to the Filament admin sign-in page. This route simply redirects to
// the Filament admin base path which serves the login UI.
Route::middleware('throttle:login')->get('/login', function () {
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

// Reports preview routes
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::get('/reports/stock-report/preview', [StockReportController::class, 'preview'])
        ->name('reports.stock-report.preview');

    // Financial report standalone previews (no Filament layout)
    Route::get('/reports/balance-sheet/preview',   [FinancialReportPreviewController::class, 'balanceSheet'])->name('reports.balance-sheet.preview');
    Route::get('/reports/profit-and-loss/preview', [FinancialReportPreviewController::class, 'profitAndLoss'])->name('reports.profit-and-loss.preview');
    Route::get('/reports/cash-flow/preview',       [FinancialReportPreviewController::class, 'cashFlow'])->name('reports.cash-flow.preview');
    Route::get('/reports/hpp/preview',             [FinancialReportPreviewController::class, 'hpp'])->name('reports.hpp.preview');
    Route::get('/reports/ageing-report/preview',   [FinancialReportPreviewController::class, 'ageingReport'])->name('reports.ageing-report.preview');

    // Kartu Persediaan — print / PDF / Excel
    Route::get('/reports/inventory-card/print',          [InventoryCardController::class, 'printView'])->name('inventory-card.print');
    Route::get('/reports/inventory-card/download-pdf',   [InventoryCardController::class, 'downloadPdf'])->name('inventory-card.pdf');
    Route::get('/reports/inventory-card/download-excel', [InventoryCardController::class, 'downloadExcel'])->name('inventory-card.excel');
});