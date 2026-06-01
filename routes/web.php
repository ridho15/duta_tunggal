<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Appearance;
use App\Http\Controllers\Reports\StockReportController;
use App\Http\Controllers\Reports\FinancialReportPreviewController;
use App\Http\Controllers\Reports\TrialBalancePreviewController;
use App\Http\Controllers\Reports\CogmPreviewController;
use App\Http\Controllers\InventoryCardController;
use App\Http\Controllers\PdfPreviewController;

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

    Route::get('/reports/trial-balance/preview', [TrialBalancePreviewController::class, 'preview'])
        ->name('reports.trial-balance.preview');

    Route::get('/reports/cogm/preview', [CogmPreviewController::class, 'preview'])
        ->name('reports.cogm.preview');

    // Financial report standalone previews (no Filament layout)
    Route::get('/reports/financial-statement/preview', [FinancialReportPreviewController::class, 'financialStatement'])->name('reports.financial-statement.preview');
    Route::get('/reports/financial-statement/download-pdf', [FinancialReportPreviewController::class, 'financialStatementPdf'])->name('reports.financial-statement.pdf');
    Route::get('/reports/financial-statement/download-excel', [FinancialReportPreviewController::class, 'financialStatementExcel'])->name('reports.financial-statement.excel');
    Route::get('/reports/drill-down-financial-report/preview', [FinancialReportPreviewController::class, 'drillDownFinancialReport'])->name('reports.drill-down-financial-report.preview');
    Route::get('/reports/drill-down-financial-report/download-pdf', [FinancialReportPreviewController::class, 'drillDownFinancialReportPdf'])->name('reports.drill-down-financial-report.pdf');
    Route::get('/reports/drill-down-financial-report/download-excel', [FinancialReportPreviewController::class, 'drillDownFinancialReportExcel'])->name('reports.drill-down-financial-report.excel');
    Route::get('/reports/alk-grafik/preview', [FinancialReportPreviewController::class, 'alkGrafik'])->name('reports.alk-grafik.preview');
    Route::get('/reports/alk-grafik/download-pdf', [FinancialReportPreviewController::class, 'alkGrafikPdf'])->name('reports.alk-grafik.pdf');
    Route::get('/reports/alk-grafik/download-excel', [FinancialReportPreviewController::class, 'alkGrafikExcel'])->name('reports.alk-grafik.excel');
    Route::get('/reports/balance-sheet/preview',   [FinancialReportPreviewController::class, 'balanceSheet'])->name('reports.balance-sheet.preview');
    Route::get('/reports/profit-and-loss/preview', [FinancialReportPreviewController::class, 'profitAndLoss'])->name('reports.profit-and-loss.preview');
    Route::get('/reports/profit-loss-multi-division/preview', [FinancialReportPreviewController::class, 'profitLossMultiDivision'])->name('reports.profit-loss-multi-division.preview');
    Route::get('/reports/profit-loss-multi-division/download-excel', [FinancialReportPreviewController::class, 'profitLossMultiDivisionExcel'])->name('reports.profit-loss-multi-division.excel');
    Route::get('/reports/cash-flow/preview',       [FinancialReportPreviewController::class, 'cashFlow'])->name('reports.cash-flow.preview');
    Route::get('/reports/hpp/preview',             [FinancialReportPreviewController::class, 'hpp'])->name('reports.hpp.preview');
    Route::get('/reports/ageing-report/preview',   [FinancialReportPreviewController::class, 'ageingReport'])->name('reports.ageing-report.preview');
    Route::get('/reports/journal-consolidation/preview', [FinancialReportPreviewController::class, 'journalConsolidation'])->name('reports.journal-consolidation.preview');

    // Kartu Persediaan — print / PDF / Excel
    Route::get('/reports/inventory-card/print',          [InventoryCardController::class, 'printView'])->name('inventory-card.print');
    Route::get('/reports/inventory-card/download-pdf',   [InventoryCardController::class, 'downloadPdf'])->name('inventory-card.pdf');
    Route::get('/reports/inventory-card/download-excel', [InventoryCardController::class, 'downloadExcel'])->name('inventory-card.excel');

    // Document PDF streaming (opens in new tab)
    Route::get('/pdf/{type}/{id}', [PdfPreviewController::class, 'stream'])
        ->name('pdf-stream')
        ->where('type', 'order-request|purchase-order|purchase-invoice|quotation|sale-order|sales-invoice|delivery-order|delivery-schedule|surat-jalan');

    // Customer Return PDF streaming
    Route::get('/pdf/customer-return/{id}', function (int $id) {
        $return = \App\Models\CustomerReturn::with([
            'invoice', 'customer', 'cabang', 'customerReturnItems.product',
            'receivedBy', 'qcInspectedBy', 'approvedBy'
        ])->findOrFail($id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.customer-return', ['return' => $return])->setPaper('a4', 'portrait');
        return $pdf->stream("CustomerReturn_{$return->return_number}.pdf");
    })->name('pdf-customer-return');
});