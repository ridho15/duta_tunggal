<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\StockCard;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\VendorPayment;
use Illuminate\Support\Facades\DB;

echo "====================================================================\n";
echo " SDLC PROMAX AUDIT: SIKLUS A (RANTAI PENGADAAN & PENERIMAAN BARANG) \n";
echo "====================================================================\n\n";

$results = [
    'po_audit' => [],
    'grn_audit' => [],
    'invoice_audit' => [],
    'payment_audit' => [],
    'journal_integrity' => [],
    'stock_movement_integrity' => [],
    'passed' => true,
    'errors' => []
];

// 1. Audit Purchase Orders
echo "[1/6] Mengaudit Dokumen Purchase Order (PO)...\n";
$poCount = PurchaseOrder::count();
$approvedPoCount = PurchaseOrder::where('status', 'approved')->count();
echo "  - Total PO di database: {$poCount}\n";
echo "  - Total PO berstatus 'approved': {$approvedPoCount}\n";

$testPo = PurchaseOrder::with(['supplier', 'purchaseOrderItem.product'])->find(13);
if ($testPo) {
    echo "  - PO Target Siklus A: ID 13 ({$testPo->po_number})\n";
    echo "    Supplier: {$testPo->supplier?->perusahaan} (ID: {$testPo->supplier_id})\n";
    echo "    Total Nilai: Rp " . number_format((float)$testPo->total_amount, 2, ',', '.') . "\n";
    echo "    Jumlah Item: " . $testPo->purchaseOrderItem->count() . "\n";
    foreach ($testPo->purchaseOrderItem as $item) {
        echo "      * {$item->product?->name} | Qty: {$item->quantity} | Harga: Rp " . number_format((float)$item->unit_price, 2, ',', '.') . "\n";
    }
    $results['po_audit']['target_po_found'] = true;
} else {
    $results['po_audit']['target_po_found'] = false;
    $results['errors'][] = "PO ID 13 tidak ditemukan.";
}

// 2. Audit Penerimaan Barang (GRN / Goods Receipt)
echo "\n[2/6] Mengaudit Penerimaan Barang (Purchase Receipts)...\n";
$grnCount = PurchaseReceipt::count();
echo "  - Total GRN saat ini: {$grnCount}\n";

$receipts = PurchaseReceipt::with(['purchaseReceiptItem', 'purchaseOrder'])->get();
foreach ($receipts as $rcp) {
    echo "  - GRN {$rcp->receipt_number} | Status: {$rcp->status} | PO: {$rcp->purchaseOrder?->po_number}\n";
    foreach ($rcp->purchaseReceiptItem as $rItem) {
        echo "    * Produk ID {$rItem->product_id} | Tiba: {$rItem->qty_received} | Diterima: {$rItem->qty_accepted} | Ditolak: {$rItem->qty_rejected}\n";
    }
}
$results['grn_audit']['count'] = $grnCount;

// 3. Audit Faktur Pembelian (Purchase Invoices)
echo "\n[3/6] Mengaudit Faktur Pembelian (Purchase Invoices)...\n";
$purchaseInvoices = Invoice::with(['accountPayable', 'invoiceItem', 'supplier'])
    ->where(function ($q) {
        $q->where('from_model_type', PurchaseOrder::class)
          ->orWhere('from_model_type', 'App\Models\PurchaseOrder')
          ->orWhereNotNull('purchase_receipts');
    })
    ->get();

echo "  - Total Faktur Pembelian: " . $purchaseInvoices->count() . "\n";
foreach ($purchaseInvoices as $inv) {
    $ap = $inv->accountPayable;
    echo "  - Faktur: {$inv->invoice_number} | Status: {$inv->status} | Total: Rp " . number_format((float)$inv->total, 2, ',', '.') . "\n";
    echo "    Supplier: {$inv->supplier?->perusahaan} | No Faktur Supplier: " . ($inv->supplier_invoice_number ?: '-') . "\n";
    if ($ap) {
        echo "    Kartu Hutang (AP): Awal Rp " . number_format((float)$ap->amount, 2, ',', '.') .
             " | Terbayar Rp " . number_format((float)$ap->paid, 2, ',', '.') .
             " | Sisa Rp " . number_format((float)$ap->remaining, 2, ',', '.') . "\n";
    } else {
        echo "    [WARNING] Kartu Hutang (AP) belum tercatat untuk invoice ini.\n";
    }
}
$results['invoice_audit']['count'] = $purchaseInvoices->count();

// 4. Audit Pembayaran Hutang Vendor (Vendor Payments)
echo "\n[4/6] Mengaudit Pembayaran Hutang Vendor (Vendor Payments)...\n";
$vendorPayments = VendorPayment::with(['vendorPaymentDetail.invoice', 'supplier', 'coa'])->get();
echo "  - Total Pembayaran Vendor: " . $vendorPayments->count() . "\n";
foreach ($vendorPayments as $vp) {
    echo "  - Pembayaran: {$vp->payment_number} | Metode: {$vp->payment_method} | Status: {$vp->status}\n";
    echo "    Akun Kas/Bank: {$vp->coa?->name} ({$vp->coa?->code})\n";
    echo "    Total Dibayar: Rp " . number_format((float)$vp->total_payment, 2, ',', '.') . "\n";
    foreach ($vp->vendorPaymentDetail as $detail) {
        echo "      * Faktur: {$detail->invoice?->invoice_number} | Alokasi: Rp " . number_format((float)$detail->payment_amount, 2, ',', '.') . "\n";
    }
}
$results['payment_audit']['count'] = $vendorPayments->count();

// 5. Audit Keseimbangan Jurnal Akuntansi (Debit = Credit)
echo "\n[5/6] Mengaudit Integritas Jurnal Akuntansi (Debit = Credit)...\n";
$unbalancedJournals = DB::table('journal_entries')
    ->select('transaction_id', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'), DB::raw('ABS(SUM(debit) - SUM(credit)) as diff'))
    ->whereNotNull('transaction_id')
    ->groupBy('transaction_id')
    ->havingRaw('diff > 0.01')
    ->get();

if ($unbalancedJournals->isEmpty()) {
    echo "  [OK] Semua entri jurnal akuntansi seimbang secara presisi (Debit == Credit).\n";
    $results['journal_integrity']['unbalanced_count'] = 0;
} else {
    echo "  [FAIL] Ditemukan " . $unbalancedJournals->count() . " entri jurnal tidak seimbang:\n";
    foreach ($unbalancedJournals as $uj) {
        echo "    * Transaksi {$uj->transaction_id} | Debit: {$uj->total_debit} | Credit: {$uj->total_credit} | Selisih: {$uj->diff}\n";
    }
    $results['journal_integrity']['unbalanced_count'] = $unbalancedJournals->count();
    $results['passed'] = false;
    $results['errors'][] = "Terdapat entri jurnal tidak seimbang.";
}

// 6. Audit Kartu Stok Fisik & Mutasi Gudang
echo "\n[6/6] Mengaudit Kartu Stok Fisik (Stock Movements)...\n";
$hasStockTable = Schema::hasTable('stock_movements') || Schema::hasTable('stock_cards');
echo "  - Tabel pencatatan mutasi stok tersedia: " . ($hasStockTable ? 'YA' : 'TIDAK') . "\n";

echo "\n====================================================================\n";
echo " RINGKASAN AUDIT SIKLUS A: " . ($results['passed'] ? "SIAP DIJALANKAN (PASS)" : "TERDAPAT CATATAN") . "\n";
echo "====================================================================\n";

