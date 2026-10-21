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

        Route::get('/do/create', [\App\Http\Controllers\Penjualan\DeliveryOrderController::class, 'create'])->name('do.create');
        Route::post('/do', [\App\Http\Controllers\Penjualan\DeliveryOrderController::class, 'store'])->name('do.store');
        Route::get('/do', function () { return redirect()->route('penjualan.so.index'); })->name('do.index');

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

        Route::get('/penawaran', function () { return redirect()->route('dashboard'); })->name('quotations.index');
    });

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

        Route::get('/stok', function () { return redirect()->route('dashboard'); })->name('stok.index');
        Route::get('/transfer', function () { return redirect()->route('dashboard'); })->name('transfer.index');
        Route::get('/opname', function () { return redirect()->route('dashboard'); })->name('opname.index');
    });

    // Modul Pembelian
    Route::prefix('pembelian')->name('pembelian.')->group(function () {
        Route::get('/permintaan', function () { return redirect()->route('dashboard'); })->name('or.index');

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
    });

    // Modul Keuangan & Akuntansi
    Route::prefix('keuangan')->name('keuangan.')->group(function () {
        Route::get('/kas-bank', function () { return redirect()->route('dashboard'); })->name('cashbank.index');
        Route::get('/jurnal', function () { return redirect()->route('dashboard'); })->name('journal.index');
    });

    // Modul Laporan
    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/keuangan', function () { return redirect()->route('dashboard'); })->name('financial.index');
    });

    // Master Data
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/produk', function () { return redirect()->route('dashboard'); })->name('products.index');
        Route::get('/customer', function () { return redirect()->route('dashboard'); })->name('customers.index');
        Route::get('/supplier', function () { return redirect()->route('dashboard'); })->name('suppliers.index');
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