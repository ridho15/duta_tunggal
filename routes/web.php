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

use App\Http\Controllers\DashboardController;

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
});

use App\Http\Controllers\Auth\LoginController;

// Rute Autentikasi Pengguna
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Rute Home untuk Pengguna Terotentikasi
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    })->name('home');
});

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

if (app()->environment('local')) {
    Route::get('/dev-autologin', function () {
        $user = \App\Models\User::first();
        if ($user) {
            \Illuminate\Support\Facades\Auth::login($user);
        }
        return redirect('/admin/order-requests/create');
    });

    Route::get('/dev-autologin-po', function () {
        $user = \App\Models\User::first();
        if ($user) {
            \Illuminate\Support\Facades\Auth::login($user);
        }
        return redirect('/admin/purchase-orders/create');
    });

    Route::get('/dev-autologin-qo', function () {
        $user = \App\Models\User::first();
        if ($user) {
            \Illuminate\Support\Facades\Auth::login($user);
        }
        return redirect('/admin/quotations/create');
    });

    Route::get('/dev-autologin-so', function () {
        $user = \App\Models\User::first();
        if ($user) {
            \Illuminate\Support\Facades\Auth::login($user);
        }
        return redirect('/admin/sale-orders/create');
    });

    Route::get('/dev-login', function () {
        $user = \App\Models\User::first();
        if ($user) {
            \Illuminate\Support\Facades\Auth::login($user);
        }
        return redirect()->route('dashboard');
    });
}

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

    // Bukti penerimaan customer (disk privat, berotorisasi lewat CustomerReceiptPolicy::view)
    Route::get('/customer-receipts/{receipt}/proof', [\App\Http\Controllers\CustomerReceiptProofController::class, 'show'])
        ->name('customer-receipts.proof')
        ->whereNumber('receipt');

    // Document PDF streaming (opens in new tab)
    Route::get('/pdf/{type}/{id}', [PdfPreviewController::class, 'stream'])
        ->name('pdf-stream')
        ->where('type', 'order-request|purchase-order|purchase-invoice|quotation|sale-order|sales-invoice|delivery-order|delivery-schedule|surat-jalan|credit-note|customer-receipt');

    // Customer Return PDF streaming
    Route::get('/pdf/customer-return/{id}', function (int $id) {
        $return = \App\Models\CustomerReturn::with([
            'invoice', 'customer', 'cabang', 'customerReturnItems.product',
            'receivedBy', 'qcInspectedBy', 'approvedBy'
        ])->findOrFail($id);

        \Illuminate\Support\Facades\Gate::authorize('view', $return);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.customer-return', [
            'return' => $return,
            'doc' => app(\App\Services\DocumentPrintBuilder::class)->customerReturn($return),
        ])->setPaper('a4', 'portrait');
        return $pdf->stream("CustomerReturn_{$return->return_number}.pdf");
    })->name('pdf-customer-return');

    // Tahap 3 — picking list per gudang (PDF)
    Route::get('/pdf/picking-list', \App\Http\Controllers\Hp\PickingListController::class)->name('pdf.picking-list');

    // ==========================================
    // RUTE WEB MODUL ERP (Custom Blade + Tailwind)
    // ==========================================

    // Modul Penjualan
    Route::prefix('penjualan')->name('penjualan.')->group(function () {
        Route::get('/so', [\App\Http\Controllers\Penjualan\SaleOrderController::class, 'index'])->name('so.index');
        Route::get('/so/create', [\App\Http\Controllers\Penjualan\SaleOrderController::class, 'create'])->name('so.create');
        Route::post('/so', [\App\Http\Controllers\Penjualan\SaleOrderController::class, 'store'])->name('so.store');
        Route::get('/so/{saleOrder}', [\App\Http\Controllers\Penjualan\SaleOrderController::class, 'show'])->name('so.show');
        Route::post('/so/{saleOrder}/approve', [\App\Http\Controllers\Penjualan\SaleOrderController::class, 'approve'])->name('so.approve');
        Route::post('/so/{saleOrder}/reject', [\App\Http\Controllers\Penjualan\SaleOrderController::class, 'reject'])->name('so.reject');

        // Perintah Kirim (Delivery Order / DO)
        Route::get('/do', [\App\Http\Controllers\Penjualan\DeliveryOrderController::class, 'index'])->name('do.index');
        Route::get('/do/create', [\App\Http\Controllers\Penjualan\DeliveryOrderController::class, 'create'])->name('do.create');
        Route::post('/do', [\App\Http\Controllers\Penjualan\DeliveryOrderController::class, 'store'])->name('do.store');
        Route::get('/do/{deliveryOrder}', [\App\Http\Controllers\Penjualan\DeliveryOrderController::class, 'show'])->name('do.show');
        Route::get('/do/{deliveryOrder}/pdf', [\App\Http\Controllers\Penjualan\DeliveryOrderController::class, 'pdf'])->name('do.pdf');
        Route::post('/do/{deliveryOrder}/cancel', [\App\Http\Controllers\Penjualan\DeliveryOrderController::class, 'cancel'])->name('do.cancel');

        // Aliases for English / plural URL compatibility
        Route::get('/delivery-orders', function () { return redirect()->route('penjualan.do.index'); });
        Route::get('/delivery-orders/create', function () { return redirect()->route('penjualan.do.create', request()->query()); });
        Route::get('/delivery-orders/{deliveryOrder}', function ($deliveryOrder) { return redirect()->route('penjualan.do.show', $deliveryOrder); });

        // Surat Jalan
        Route::get('/surat-jalan', [\App\Http\Controllers\Penjualan\SuratJalanController::class, 'index'])->name('sj.index');
        Route::get('/surat-jalan/create', [\App\Http\Controllers\Penjualan\SuratJalanController::class, 'create'])->name('sj.create');
        Route::post('/surat-jalan', [\App\Http\Controllers\Penjualan\SuratJalanController::class, 'store'])->name('sj.store');
        Route::get('/surat-jalan/{suratJalan}', [\App\Http\Controllers\Penjualan\SuratJalanController::class, 'show'])->name('sj.show');
        Route::get('/surat-jalan/{suratJalan}/pdf', [\App\Http\Controllers\Penjualan\SuratJalanController::class, 'pdf'])->name('sj.pdf');
        Route::post('/surat-jalan/{suratJalan}/cancel', [\App\Http\Controllers\Penjualan\SuratJalanController::class, 'cancel'])->name('sj.cancel');

        // Faktur Penjualan (Sales Invoice)
        Route::get('/invoice', [\App\Http\Controllers\Penjualan\SalesInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoice/create', [\App\Http\Controllers\Penjualan\SalesInvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoice', [\App\Http\Controllers\Penjualan\SalesInvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/invoice/{invoice}', [\App\Http\Controllers\Penjualan\SalesInvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoice/{invoice}/pdf', [\App\Http\Controllers\Penjualan\SalesInvoiceController::class, 'pdf'])->name('invoices.pdf');

        // Aliases for plural URL compatibility
        Route::get('/invoices', function () { return redirect()->route('penjualan.invoices.index'); });
        Route::get('/invoices/create', function () { return redirect()->route('penjualan.invoices.create', request()->query()); });
        Route::get('/invoices/{invoice}', function ($invoice) { return redirect()->route('penjualan.invoices.show', $invoice); });

        // Terima Pembayaran Customer (Pelunasan Piutang)
        Route::get('/terima-pembayaran', [\App\Http\Controllers\Penjualan\CustomerReceiptController::class, 'index'])->name('receipts.index');
        Route::get('/terima-pembayaran/create', [\App\Http\Controllers\Penjualan\CustomerReceiptController::class, 'create'])->name('receipts.create');
        Route::get('/terima-pembayaran/customer-invoices/{customer}', [\App\Http\Controllers\Penjualan\CustomerReceiptController::class, 'customerInvoices'])->name('receipts.customerInvoices');
        Route::post('/terima-pembayaran', [\App\Http\Controllers\Penjualan\CustomerReceiptController::class, 'store'])->name('receipts.store');
        Route::get('/terima-pembayaran/{customerReceipt}', [\App\Http\Controllers\Penjualan\CustomerReceiptController::class, 'show'])->name('receipts.show');
        Route::get('/terima-pembayaran/{customerReceipt}/pdf', [\App\Http\Controllers\Penjualan\CustomerReceiptController::class, 'pdf'])->name('receipts.pdf');
        Route::post('/terima-pembayaran/{customerReceipt}/cancel', [\App\Http\Controllers\Penjualan\CustomerReceiptController::class, 'cancel'])->name('receipts.cancel');

        // Aliases for plural URL compatibility
        Route::get('/receipts', function () { return redirect()->route('penjualan.receipts.index'); });
        Route::get('/receipts/create', function () { return redirect()->route('penjualan.receipts.create', request()->query()); });
        Route::get('/receipts/{customerReceipt}', function ($customerReceipt) { return redirect()->route('penjualan.receipts.show', $customerReceipt); });

        // Retur Penjualan (Customer Return)
        Route::get('/retur', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'index'])->name('returns.index');
        Route::get('/retur/create', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'create'])->name('returns.create');
        Route::get('/retur/customer-invoices/{customer}', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'customerInvoices'])->name('returns.customerInvoices');
        Route::post('/retur', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'store'])->name('returns.store');
        Route::get('/retur/{customerReturn}', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'show'])->name('returns.show');
        Route::get('/retur/{customerReturn}/pdf', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'pdf'])->name('returns.pdf');
        Route::post('/retur/{customerReturn}/mark-received', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'markReceived'])->name('returns.markReceived');
        Route::post('/retur/{customerReturn}/start-qc', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'startQc'])->name('returns.startQc');
        Route::post('/retur/{customerReturn}/save-qc', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'saveQc'])->name('returns.saveQc');
        Route::post('/retur/{customerReturn}/approve', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'approve'])->name('returns.approve');
        Route::post('/retur/{customerReturn}/reject', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'reject'])->name('returns.reject');
        Route::post('/retur/{customerReturn}/complete', [\App\Http\Controllers\Penjualan\CustomerReturnController::class, 'complete'])->name('returns.complete');

        // Aliases for plural URL compatibility
        Route::get('/returns', function () { return redirect()->route('penjualan.returns.index'); });
        Route::get('/returns/create', function () { return redirect()->route('penjualan.returns.create', request()->query()); });
        Route::get('/returns/{customerReturn}', function ($customerReturn) { return redirect()->route('penjualan.returns.show', $customerReturn); });

        // Nota Kredit (Credit Note)
        Route::get('/nota-kredit', [\App\Http\Controllers\Penjualan\CreditNoteController::class, 'index'])->name('credit-notes.index');
        Route::get('/nota-kredit/create', [\App\Http\Controllers\Penjualan\CreditNoteController::class, 'create'])->name('credit-notes.create');
        Route::get('/nota-kredit/customer-invoices/{customer}', [\App\Http\Controllers\Penjualan\CreditNoteController::class, 'customerInvoices'])->name('credit-notes.customerInvoices');
        Route::get('/nota-kredit/invoice-details/{invoice}', [\App\Http\Controllers\Penjualan\CreditNoteController::class, 'invoiceDetails'])->name('credit-notes.invoiceDetails');
        Route::post('/nota-kredit', [\App\Http\Controllers\Penjualan\CreditNoteController::class, 'store'])->name('credit-notes.store');
        Route::post('/nota-kredit/from-return/{customerReturn}', [\App\Http\Controllers\Penjualan\CreditNoteController::class, 'storeFromReturn'])->name('credit-notes.fromReturn');
        Route::get('/nota-kredit/{creditNote}', [\App\Http\Controllers\Penjualan\CreditNoteController::class, 'show'])->name('credit-notes.show');
        Route::post('/nota-kredit/{creditNote}/issue', [\App\Http\Controllers\Penjualan\CreditNoteController::class, 'issue'])->name('credit-notes.issue');
        Route::post('/nota-kredit/{creditNote}/tax-document', [\App\Http\Controllers\Penjualan\CreditNoteController::class, 'updateTaxDocument'])->name('credit-notes.updateTaxDocument');
        Route::delete('/nota-kredit/{creditNote}', [\App\Http\Controllers\Penjualan\CreditNoteController::class, 'destroy'])->name('credit-notes.destroy');
        Route::get('/nota-kredit/{creditNote}/pdf', [\App\Http\Controllers\Penjualan\CreditNoteController::class, 'pdf'])->name('credit-notes.pdf');

        // Aliases for English / plural URL compatibility
        Route::get('/credit-notes', function () { return redirect()->route('penjualan.credit-notes.index'); });
        Route::get('/credit-notes/create', function () { return redirect()->route('penjualan.credit-notes.create', request()->query()); });
        Route::get('/credit-notes/{creditNote}', function ($creditNote) { return redirect()->route('penjualan.credit-notes.show', $creditNote); });

        // Penawaran Harga (Quotation)
        Route::prefix('penawaran')->name('quotations.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Penjualan\QuotationController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Penjualan\QuotationController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Penjualan\QuotationController::class, 'store'])->name('store');
            Route::get('/{quotation}', [\App\Http\Controllers\Penjualan\QuotationController::class, 'show'])->name('show');
            Route::get('/{quotation}/edit', [\App\Http\Controllers\Penjualan\QuotationController::class, 'edit'])->name('edit');
            Route::put('/{quotation}', [\App\Http\Controllers\Penjualan\QuotationController::class, 'update'])->name('update');
            Route::delete('/{quotation}', [\App\Http\Controllers\Penjualan\QuotationController::class, 'destroy'])->name('destroy');
            Route::post('/{quotation}/request-approve', [\App\Http\Controllers\Penjualan\QuotationController::class, 'requestApprove'])->name('requestApprove');
            Route::post('/{quotation}/approve', [\App\Http\Controllers\Penjualan\QuotationController::class, 'approve'])->name('approve');
            Route::post('/{quotation}/reject', [\App\Http\Controllers\Penjualan\QuotationController::class, 'reject'])->name('reject');
            Route::post('/{quotation}/revision', [\App\Http\Controllers\Penjualan\QuotationController::class, 'createRevision'])->name('revision');
            Route::get('/{quotation}/convert-so', [\App\Http\Controllers\Penjualan\QuotationController::class, 'convertToSaleOrder'])->name('convertToSaleOrder');
            Route::get('/{quotation}/pdf', [\App\Http\Controllers\Penjualan\QuotationController::class, 'pdf'])->name('pdf');
        });

        // Aliases for English / plural URL compatibility
        Route::get('/quotations', function () { return redirect()->route('penjualan.quotations.index'); });
        Route::get('/quotations/create', function () { return redirect()->route('penjualan.quotations.create', request()->query()); });
        Route::get('/quotations/{quotation}', function ($quotation) { return redirect()->route('penjualan.quotations.show', $quotation); });
    });

    // Modul Kasir / Point of Sale (POS)
    Route::prefix('kasir')->name('kasir.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Kasir\PosController::class, 'index'])->name('pos.index');
        Route::get('/cari-produk', [\App\Http\Controllers\Kasir\PosController::class, 'searchProduct'])->name('pos.search');
        Route::post('/transaksi', [\App\Http\Controllers\Kasir\PosController::class, 'store'])->name('pos.store');
        Route::get('/struk/{posOrder}', [\App\Http\Controllers\Kasir\PosController::class, 'receipt'])->name('pos.receipt');
        Route::get('/riwayat', [\App\Http\Controllers\Kasir\PosController::class, 'history'])->name('pos.history');
        Route::get('/detail/{posOrder}', [\App\Http\Controllers\Kasir\PosController::class, 'show'])->name('pos.show');

        // Shift Kasir
        Route::post('/shift/buka', [\App\Http\Controllers\Kasir\PosShiftController::class, 'open'])->name('shift.open');
        Route::post('/shift/tutup', [\App\Http\Controllers\Kasir\PosShiftController::class, 'close'])->name('shift.close');
        Route::get('/shift/{posShift}/rekap', [\App\Http\Controllers\Kasir\PosShiftController::class, 'summary'])->name('shift.summary');
    });

    Route::get('/pos', function () { return redirect()->route('kasir.pos.index'); });

    // Modul Gudang & Stok
    Route::prefix('gudang')->name('gudang.')->group(function () {
        Route::get('/kiriman', [\App\Http\Controllers\Gudang\KirimanController::class, 'index'])->name('kiriman.index');
        Route::get('/kiriman/picking-list', [\App\Http\Controllers\Gudang\KirimanController::class, 'pickingList'])->name('kiriman.pickingList');
        Route::get('/kiriman/{deliveryOrder}/check', [\App\Http\Controllers\Gudang\KirimanController::class, 'check'])->name('kiriman.check');
        Route::post('/kiriman/{deliveryOrder}/check', [\App\Http\Controllers\Gudang\KirimanController::class, 'submitCheck'])->name('kiriman.submitCheck');
        Route::get('/kiriman/{deliveryOrder}/retur', [\App\Http\Controllers\Gudang\KirimanController::class, 'retur'])->name('kiriman.retur');
        Route::post('/kiriman/{deliveryOrder}/retur', [\App\Http\Controllers\Gudang\KirimanController::class, 'submitRetur'])->name('kiriman.submitRetur');
        // Penerimaan Barang (Goods Receipt / Surat Jalan Masuk)
        Route::get('/penerimaan', [\App\Http\Controllers\Gudang\PenerimaanBarangController::class, 'index'])->name('penerimaan.index');
        Route::get('/penerimaan/create', [\App\Http\Controllers\Gudang\PenerimaanBarangController::class, 'create'])->name('penerimaan.create');
        Route::post('/penerimaan', [\App\Http\Controllers\Gudang\PenerimaanBarangController::class, 'store'])->name('penerimaan.store');
        Route::get('/penerimaan/{penerimaan}', [\App\Http\Controllers\Gudang\PenerimaanBarangController::class, 'show'])->name('penerimaan.show');
        Route::get('/penerimaan/{penerimaan}/edit', [\App\Http\Controllers\Gudang\PenerimaanBarangController::class, 'edit'])->name('penerimaan.edit');
        Route::put('/penerimaan/{penerimaan}', [\App\Http\Controllers\Gudang\PenerimaanBarangController::class, 'update'])->name('penerimaan.update');
        Route::delete('/penerimaan/{penerimaan}', [\App\Http\Controllers\Gudang\PenerimaanBarangController::class, 'destroy'])->name('penerimaan.destroy');
        Route::post('/penerimaan/{penerimaan}/confirm', [\App\Http\Controllers\Gudang\PenerimaanBarangController::class, 'confirm'])->name('penerimaan.confirm');
        Route::get('/penerimaan/{penerimaan}/pdf', [\App\Http\Controllers\Gudang\PenerimaanBarangController::class, 'pdf'])->name('penerimaan.pdf');
        Route::get('/penerimaan-po-items/{purchaseOrder}', [\App\Http\Controllers\Gudang\PenerimaanBarangController::class, 'poItems'])->name('penerimaan.po-items');

        // Cek Stok Real-Time & Kartu Persediaan
        Route::get('/stok', [\App\Http\Controllers\Gudang\StockController::class, 'index'])->name('stok.index');
        Route::get('/stok/kartu-persediaan', [\App\Http\Controllers\Gudang\StockController::class, 'inventoryCardReport'])->name('stok.inventory-card');
        Route::get('/stok/kartu-persediaan/pdf', [\App\Http\Controllers\Gudang\StockController::class, 'inventoryCardReportPdf'])->name('stok.inventory-card.pdf');
        Route::get('/stok/{product}/kartu', [\App\Http\Controllers\Gudang\StockController::class, 'stockCard'])->name('stok.card');
        Route::get('/stok/{product}/kartu/pdf', [\App\Http\Controllers\Gudang\StockController::class, 'stockCardPdf'])->name('stok.card.pdf');
        Route::get('/stok/{product}/kartu/excel', [\App\Http\Controllers\Gudang\StockController::class, 'stockCardExcel'])->name('stok.card.excel');

        // Pindah Stok / Transfer Antar Gudang
        Route::get('/transfer', [\App\Http\Controllers\Gudang\StockTransferController::class, 'index'])->name('transfer.index');
        Route::get('/transfer/create', [\App\Http\Controllers\Gudang\StockTransferController::class, 'create'])->name('transfer.create');
        Route::post('/transfer', [\App\Http\Controllers\Gudang\StockTransferController::class, 'store'])->name('transfer.store');
        Route::get('/transfer/{transfer}', [\App\Http\Controllers\Gudang\StockTransferController::class, 'show'])->name('transfer.show');
        Route::get('/transfer/{transfer}/edit', [\App\Http\Controllers\Gudang\StockTransferController::class, 'edit'])->name('transfer.edit');
        Route::put('/transfer/{transfer}', [\App\Http\Controllers\Gudang\StockTransferController::class, 'update'])->name('transfer.update');
        Route::delete('/transfer/{transfer}', [\App\Http\Controllers\Gudang\StockTransferController::class, 'destroy'])->name('transfer.destroy');
        Route::post('/transfer/{transfer}/request', [\App\Http\Controllers\Gudang\StockTransferController::class, 'requestTransfer'])->name('transfer.request');
        Route::post('/transfer/{transfer}/approve', [\App\Http\Controllers\Gudang\StockTransferController::class, 'approve'])->name('transfer.approve');
        Route::post('/transfer/{transfer}/reject', [\App\Http\Controllers\Gudang\StockTransferController::class, 'reject'])->name('transfer.reject');
        Route::get('/transfer/{transfer}/pdf', [\App\Http\Controllers\Gudang\StockTransferController::class, 'pdf'])->name('transfer.pdf');
        Route::get('/transfer-available-products/{warehouse}', [\App\Http\Controllers\Gudang\StockTransferController::class, 'availableProducts'])->name('transfer.available-products');
        Route::get('/warehouse-raks/{warehouse}', [\App\Http\Controllers\Gudang\StockTransferController::class, 'warehouseRaks'])->name('warehouse-raks');

        // Stock Opname & Penyesuaian Persediaan
        Route::get('/opname', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'index'])->name('opname.index');
        Route::get('/opname/create', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'create'])->name('opname.create');
        Route::post('/opname', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'store'])->name('opname.store');
        Route::get('/opname/{opname}', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'show'])->name('opname.show');
        Route::delete('/opname/{opname}', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'destroy'])->name('opname.destroy');
        Route::post('/opname/{opname}/start', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'startCount'])->name('opname.start');
        Route::post('/opname/{opname}/save-progress', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'saveProgress'])->name('opname.save-progress');
        Route::post('/opname/{opname}/complete', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'completeCount'])->name('opname.complete');
        Route::post('/opname/{opname}/recount', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'recount'])->name('opname.recount');
        Route::post('/opname/{opname}/approve', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'approve'])->name('opname.approve');
        Route::get('/opname/{opname}/pdf', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'pdf'])->name('opname.pdf');
        Route::get('/opname/{opname}/excel', [\App\Http\Controllers\Gudang\StockOpnameController::class, 'excel'])->name('opname.excel');
    });

    // Modul Pembelian
    Route::prefix('pembelian')->name('pembelian.')->group(function () {
        // Permintaan Pembelian (Order Request / OR)
        Route::prefix('permintaan')->name('or.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'store'])->name('store');
            Route::get('/{orderRequest}', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'show'])->name('show');
            Route::get('/{orderRequest}/edit', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'edit'])->name('edit');
            Route::put('/{orderRequest}', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'update'])->name('update');
            Route::delete('/{orderRequest}', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'destroy'])->name('destroy');
            Route::post('/{orderRequest}/request-approve', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'requestApprove'])->name('request-approve');
            Route::post('/{orderRequest}/approve', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'approve'])->name('approve');
            Route::post('/{orderRequest}/reject', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'reject'])->name('reject');
            Route::post('/{orderRequest}/close', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'close'])->name('close');
            Route::get('/{orderRequest}/pdf', [\App\Http\Controllers\Pembelian\OrderRequestController::class, 'pdf'])->name('pdf');
        });

        // Pesanan Pembelian (Purchase Order / PO)
        Route::get('/po', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'index'])->name('po.index');
        Route::get('/po/create', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'create'])->name('po.create');
        Route::post('/po', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'store'])->name('po.store');
        Route::get('/po/{purchaseOrder}', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'show'])->name('po.show');
        Route::get('/po/{purchaseOrder}/edit', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'edit'])->name('po.edit');
        Route::put('/po/{purchaseOrder}', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'update'])->name('po.update');
        Route::delete('/po/{purchaseOrder}', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'destroy'])->name('po.destroy');
        Route::post('/po/{purchaseOrder}/approve', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'approve'])->name('po.approve');
        Route::post('/po/{purchaseOrder}/request-close', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'requestClose'])->name('po.request-close');
        Route::post('/po/{purchaseOrder}/close', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'close'])->name('po.close');
        Route::get('/po/{purchaseOrder}/pdf', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'pdf'])->name('po.pdf');
        Route::get('/supplier-details/{supplier}', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'supplierDetails'])->name('supplier-details');
        Route::get('/product-details/{product}', [\App\Http\Controllers\Pembelian\PurchaseOrderController::class, 'productDetails'])->name('product-details');

        // Aliases for English / plural URL compatibility
        Route::get('/purchase-orders', function () { return redirect()->route('pembelian.po.index'); });
        Route::get('/purchase-orders/create', function () { return redirect()->route('pembelian.po.create', request()->query()); });
        Route::get('/purchase-orders/{purchaseOrder}', function ($purchaseOrder) { return redirect()->route('pembelian.po.show', $purchaseOrder); });

        // Aliases for Penerimaan Pembelian
        Route::get('/penerimaan', function () { return redirect()->route('gudang.penerimaan.index', request()->query()); })->name('receipts.index');
        Route::get('/penerimaan/create', function () { return redirect()->route('gudang.penerimaan.create', request()->query()); })->name('receipts.create');
        Route::get('/penerimaan/{penerimaan}', function ($penerimaan) { return redirect()->route('gudang.penerimaan.show', $penerimaan); })->name('receipts.show');

        // Faktur Pembelian (Purchase Invoice / AP)
        Route::get('/invoice', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoice/create', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoice', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/invoice/{invoice}', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoice/{invoice}/edit', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('/invoice/{invoice}', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('/invoice/{invoice}', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::post('/invoice/{invoice}/post', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'post'])->name('invoices.post');
        Route::post('/invoice/{invoice}/cancel', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::get('/invoice/{invoice}/pdf', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('/invoice-available-receipts/{supplier}', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'availableReceipts'])->name('invoices.available-receipts');
        Route::get('/invoice-receipt-details', [\App\Http\Controllers\Pembelian\PurchaseInvoiceController::class, 'receiptDetails'])->name('invoices.receipt-details');

        // Pembayaran Hutang Vendor (Vendor Payment / AP Settlement)
        Route::get('/pembayaran', [\App\Http\Controllers\Pembelian\VendorPaymentController::class, 'index'])->name('payments.index');
        Route::get('/pembayaran/create', [\App\Http\Controllers\Pembelian\VendorPaymentController::class, 'create'])->name('payments.create');
        Route::post('/pembayaran', [\App\Http\Controllers\Pembelian\VendorPaymentController::class, 'store'])->name('payments.store');
        Route::get('/pembayaran/{payment}', [\App\Http\Controllers\Pembelian\VendorPaymentController::class, 'show'])->name('payments.show');
        Route::get('/pembayaran/{payment}/edit', [\App\Http\Controllers\Pembelian\VendorPaymentController::class, 'edit'])->name('payments.edit');
        Route::put('/pembayaran/{payment}', [\App\Http\Controllers\Pembelian\VendorPaymentController::class, 'update'])->name('payments.update');
        Route::delete('/pembayaran/{payment}', [\App\Http\Controllers\Pembelian\VendorPaymentController::class, 'destroy'])->name('payments.destroy');
        Route::post('/pembayaran/{payment}/post', [\App\Http\Controllers\Pembelian\VendorPaymentController::class, 'post'])->name('payments.post');
        Route::post('/pembayaran/{payment}/cancel', [\App\Http\Controllers\Pembelian\VendorPaymentController::class, 'cancel'])->name('payments.cancel');
        Route::get('/pembayaran/{payment}/pdf', [\App\Http\Controllers\Pembelian\VendorPaymentController::class, 'pdf'])->name('payments.pdf');
        Route::get('/pembayaran-supplier-invoices/{supplier}', [\App\Http\Controllers\Pembelian\VendorPaymentController::class, 'supplierInvoices'])->name('payments.supplier-invoices');

        // Aliases for vendor payments
        Route::get('/vendor-payments', function () { return redirect()->route('pembelian.payments.index'); });
        Route::get('/vendor-payments/create', function () { return redirect()->route('pembelian.payments.create', request()->query()); });
        Route::get('/vendor-payments/{payment}', function ($payment) { return redirect()->route('pembelian.payments.show', $payment); });

        // Retur Pembelian & Debit Note (Purchase Return & Debit Note)
        Route::get('/retur', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'index'])->name('returns.index');
        Route::get('/retur/create', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'create'])->name('returns.create');
        Route::post('/retur', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'store'])->name('returns.store');
        Route::get('/retur/{return}', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'show'])->name('returns.show');
        Route::get('/retur/{return}/edit', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'edit'])->name('returns.edit');
        Route::put('/retur/{return}', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'update'])->name('returns.update');
        Route::delete('/retur/{return}', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'destroy'])->name('returns.destroy');
        Route::post('/retur/{return}/submit', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'submit'])->name('returns.submit');
        Route::post('/retur/{return}/approve', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'approve'])->name('returns.approve');
        Route::post('/retur/{return}/reject', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'reject'])->name('returns.reject');
        Route::post('/retur/{return}/resolution', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'updateResolution'])->name('returns.resolution');
        Route::get('/retur/{return}/pdf', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'pdf'])->name('returns.pdf');
        Route::get('/retur-supplier-receipts/{supplier}', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'supplierReceipts'])->name('returns.supplier-receipts');
        Route::get('/retur-receipt-items/{receipt}', [\App\Http\Controllers\Pembelian\PurchaseReturnController::class, 'receiptItems'])->name('returns.receipt-items');

        // Aliases for English / plural URL compatibility
        Route::get('/purchase-returns', function () { return redirect()->route('pembelian.returns.index'); });
        Route::get('/purchase-returns/create', function () { return redirect()->route('pembelian.returns.create', request()->query()); });
        Route::get('/purchase-returns/{return}', function ($return) { return redirect()->route('pembelian.returns.show', $return); });
    });

    // Modul Keuangan & Akuntansi
    Route::prefix('keuangan')->name('keuangan.')->group(function () {
        // Kas & Bank (Mutasi Masuk & Keluar)
        Route::get('/kas-bank', [\App\Http\Controllers\Keuangan\CashBankController::class, 'index'])->name('cashbank.index');
        Route::get('/kas-bank/create', [\App\Http\Controllers\Keuangan\CashBankController::class, 'create'])->name('cashbank.create');
        Route::post('/kas-bank', [\App\Http\Controllers\Keuangan\CashBankController::class, 'store'])->name('cashbank.store');
        Route::get('/kas-bank/{transaction}', [\App\Http\Controllers\Keuangan\CashBankController::class, 'show'])->name('cashbank.show');
        Route::delete('/kas-bank/{transaction}', [\App\Http\Controllers\Keuangan\CashBankController::class, 'destroy'])->name('cashbank.destroy');
        Route::get('/kas-bank/{transaction}/pdf', [\App\Http\Controllers\Keuangan\CashBankController::class, 'pdf'])->name('cashbank.pdf');

        // Transfer Antar Kas & Bank
        Route::get('/transfer/create', [\App\Http\Controllers\Keuangan\CashBankController::class, 'createTransfer'])->name('cashbank.transfer.create');
        Route::post('/transfer', [\App\Http\Controllers\Keuangan\CashBankController::class, 'storeTransfer'])->name('cashbank.transfer.store');
        Route::get('/transfer/{transfer}', [\App\Http\Controllers\Keuangan\CashBankController::class, 'showTransfer'])->name('cashbank.transfer.show');
        Route::delete('/transfer/{transfer}', [\App\Http\Controllers\Keuangan\CashBankController::class, 'destroyTransfer'])->name('cashbank.transfer.destroy');
        Route::get('/transfer/{transfer}/pdf', [\App\Http\Controllers\Keuangan\CashBankController::class, 'pdfTransfer'])->name('cashbank.transfer.pdf');

        // Aliases for compatibility
        Route::get('/kas-bank-transfer/create', function () { return redirect()->route('keuangan.cashbank.transfer.create'); });

        // Buku Besar & Jurnal Umum
        Route::get('/jurnal', [\App\Http\Controllers\Keuangan\JournalController::class, 'index'])->name('journal.index');
        Route::get('/jurnal/buku-besar', [\App\Http\Controllers\Keuangan\JournalController::class, 'ledger'])->name('journal.ledger');
        Route::get('/jurnal/create', [\App\Http\Controllers\Keuangan\JournalController::class, 'create'])->name('journal.create');
        Route::post('/jurnal', [\App\Http\Controllers\Keuangan\JournalController::class, 'store'])->name('journal.store');
        Route::get('/jurnal/{reference}', [\App\Http\Controllers\Keuangan\JournalController::class, 'show'])->name('journal.show');
        Route::delete('/jurnal/{reference}', [\App\Http\Controllers\Keuangan\JournalController::class, 'destroy'])->name('journal.destroy');
        Route::get('/jurnal/{reference}/pdf', [\App\Http\Controllers\Keuangan\JournalController::class, 'pdf'])->name('journal.pdf');
    });

    // Modul Laporan Keuangan
    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/keuangan', [\App\Http\Controllers\Laporan\FinancialReportController::class, 'index'])->name('financial.index');
        Route::get('/keuangan/neraca-saldo', [\App\Http\Controllers\Laporan\FinancialReportController::class, 'trialBalance'])->name('financial.trial-balance');
        Route::get('/keuangan/neraca-saldo/pdf', [\App\Http\Controllers\Laporan\FinancialReportController::class, 'trialBalancePdf'])->name('financial.trial-balance.pdf');
        Route::get('/keuangan/laba-rugi', [\App\Http\Controllers\Laporan\FinancialReportController::class, 'incomeStatement'])->name('financial.income-statement');
        Route::get('/keuangan/laba-rugi/pdf', [\App\Http\Controllers\Laporan\FinancialReportController::class, 'incomeStatementPdf'])->name('financial.income-statement.pdf');
        Route::get('/keuangan/neraca', [\App\Http\Controllers\Laporan\FinancialReportController::class, 'balanceSheet'])->name('financial.balance-sheet');
        Route::get('/keuangan/neraca/pdf', [\App\Http\Controllers\Laporan\FinancialReportController::class, 'balanceSheetPdf'])->name('financial.balance-sheet.pdf');
    });

    // Master Data
    Route::prefix('master')->name('master.')->group(function () {
        // Master Produk
        Route::get('/produk/api/generate-sku', [\App\Http\Controllers\Master\ProductController::class, 'generateSku'])->name('products.generate-sku');
        Route::post('/produk/{product}/toggle-active', [\App\Http\Controllers\Master\ProductController::class, 'toggleActive'])->name('products.toggle-active');
        Route::resource('produk', \App\Http\Controllers\Master\ProductController::class)
            ->names('products')
            ->parameters(['produk' => 'product']);

        // Master Customer
        Route::get('/customer/api/generate-code', [\App\Http\Controllers\Master\CustomerController::class, 'generateCode'])->name('customers.generate-code');
        Route::get('/customer/api/check-duplicates', [\App\Http\Controllers\Master\CustomerController::class, 'checkDuplicates'])->name('customers.check-duplicates');
        Route::resource('customer', \App\Http\Controllers\Master\CustomerController::class)->names('customers');

        // Master Supplier
        Route::get('/supplier/api/generate-code', [\App\Http\Controllers\Master\SupplierController::class, 'generateCode'])->name('suppliers.generate-code');
        Route::get('/supplier/api/check-duplicates', [\App\Http\Controllers\Master\SupplierController::class, 'checkDuplicates'])->name('suppliers.check-duplicates');
        Route::resource('supplier', \App\Http\Controllers\Master\SupplierController::class)->names('suppliers');
    });

    // Pengaturan
    Route::prefix('pengaturan')->name('settings.')->group(function () {
        Route::get('/pengguna', function () { return redirect()->route('dashboard'); })->name('users.index');
    });

    // Backward-compatibility redirects for eliminated Hub pages
    Route::redirect('/admin/sales-hub', '/admin/sale-orders', 301);
    Route::redirect('/admin/purchase-hub', '/admin/purchase-orders', 301);
    Route::redirect('/admin/delivery-hub', '/admin/delivery-orders', 301);
    Route::redirect('/admin/accounting-hub', '/admin/journal-entries', 301);
    Route::redirect('/admin/inventory-hub', '/admin/inventory-stocks', 301);
    Route::redirect('/admin/master-data-hub', '/admin/products', 301);
    Route::redirect('/admin/user-roles-management-hub', '/admin/users', 301);
    Route::redirect('/admin/manufacturing-hub', '/admin', 301);
    Route::redirect('/admin/finance-sales-hub', '/admin/sales-invoices', 301);
    Route::redirect('/admin/finance-purchase-hub', '/admin/purchase-invoices', 301);
    Route::redirect('/admin/payment-hub', '/admin/customer-receipts', 301);
    Route::redirect('/admin/finance-reports', '/admin/financial-statement', 301);
    Route::redirect('/admin/asset-management-hub', '/admin/assets', 301);
    Route::redirect('/admin/warehouse-hub', '/admin/warehouse-confirmations', 301);
    Route::redirect('/admin/dashboard-hub', '/admin', 301);
});