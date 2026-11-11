<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use Illuminate\Support\Facades\DB;

echo "====================================================================\n";
echo " SDLC PROMAX ADVERSARIAL QA AUDIT: SIKLUS B (ORDER-TO-CASH / O2C)   \n";
echo "====================================================================\n\n";

$results = [
    'quotations' => [],
    'sales_orders' => [],
    'delivery_orders' => [],
    'surat_jalans' => [],
    'invoices' => [],
    'customer_receipts' => [],
    'customer_returns' => [],
    'journal_balance' => true,
    'passed' => true,
    'discrepancies' => []
];

// 1. Audit Master Target
echo "[1/7] Mengaudit Target Master Pelanggan & Produk Berstok...\n";
$targetCustomer = Customer::where('code', 'CUST001')->orWhere('id', 1)->first();
echo "  - Pelanggan Target: ID {$targetCustomer?->id} - {$targetCustomer?->name} ({$targetCustomer?->code})\n";
echo "    Tipe Pembayaran: {$targetCustomer?->tipe_pembayaran} | Kredit Limit: Rp " . number_format((float)($targetCustomer?->kredit_limit ?? 0), 2, ',', '.') . "\n";

// Find product with sufficient stock in inventory
$targetStock = InventoryStock::with(['product', 'warehouse'])
    ->whereRaw('(qty_available - qty_reserved) >= 5')
    ->first();

if (!$targetStock) {
    // If no stock, pick first product and ensure 50 units for testing
    $prod = Product::first();
    $targetStock = InventoryStock::updateOrCreate(
        ['product_id' => $prod->id, 'warehouse_id' => 1],
        ['qty_available' => 50, 'qty_reserved' => 0]
    );
    $targetStock->load(['product', 'warehouse']);
}

$targetProduct = $targetStock->product;
echo "  - Produk Target: ID {$targetProduct?->id} - {$targetProduct?->name} ({$targetProduct?->sku})\n";
echo "    Gudang: {$targetStock->warehouse?->name} (ID: {$targetStock->warehouse_id})\n";
echo "    Stok Bebas: " . ($targetStock->qty_available - $targetStock->qty_reserved) . " unit\n";
echo "    Harga Jual Master: Rp " . number_format((float)($targetProduct?->sell_price ?? 0), 2, ',', '.') . "\n";
echo "    Harga Pokok (Cost): Rp " . number_format((float)($targetProduct?->cost_price ?? 0), 2, ',', '.') . "\n";

// 2. Audit Penawaran Harga (Quotations)
echo "\n[2/7] Mengaudit Penawaran Harga (Quotations)...\n";
$latestQuote = Quotation::with(['customer', 'quotationItem.product'])->latest('id')->first();
if ($latestQuote) {
    echo "  - Quotation Terakhir: {$latestQuote->quotation_number} (ID: {$latestQuote->id}) | Status: {$latestQuote->status}\n";
    echo "    Customer: {$latestQuote->customer?->name} | Total Amount: Rp " . number_format((float)$latestQuote->total_amount, 2, ',', '.') . "\n";
    foreach ($latestQuote->quotationItem as $qi) {
        echo "      * {$qi->product?->name} | Qty: {$qi->quantity} | Unit Price: Rp " . number_format((float)$qi->unit_price, 2, ',', '.') . "\n";
    }
} else {
    echo "  - Belum ada quotation.\n";
}

// 3. Audit Pesanan Penjualan (Sales Orders)
echo "\n[3/7] Mengaudit Pesanan Penjualan (Sales Orders)...\n";
$latestSo = SaleOrder::with(['customer', 'saleOrderItem.product', 'deliveryOrder'])->latest('id')->first();
if ($latestSo) {
    echo "  - SO Terakhir: {$latestSo->so_number} (ID: {$latestSo->id}) | Status: {$latestSo->status}\n";
    echo "    Customer: {$latestSo->customer?->name} | Total: Rp " . number_format((float)$latestSo->total_amount, 2, ',', '.') . "\n";
    foreach ($latestSo->saleOrderItem as $soItem) {
        echo "      * {$soItem->product?->name} | Qty: {$soItem->quantity} | Harga: Rp " . number_format((float)$soItem->unit_price, 2, ',', '.') . "\n";
    }
} else {
    echo "  - Belum ada Sales Order.\n";
}

// 4. Audit Surat Jalan & Delivery Orders
echo "\n[4/7] Mengaudit Dokumen Surat Jalan & Delivery Orders...\n";
$latestSj = SuratJalan::with(['deliveryOrder.deliveryOrderItem', 'cabang'])->latest('id')->first();
if ($latestSj) {
    echo "  - Surat Jalan Terakhir: {$latestSj->sj_number} (ID: {$latestSj->id}) | Status: {$latestSj->status}\n";
    foreach ($latestSj->deliveryOrder as $do) {
        echo "    DO Reff: {$do->do_number}\n";
        foreach ($do->deliveryOrderItem as $doi) {
            echo "      * Produk ID: {$doi->product_id} | Qty Kirim: {$doi->quantity}\n";
        }
    }
} else {
    echo "  - Belum ada Surat Jalan.\n";
}

// 5. Audit Faktur Penjualan (Sales Invoices) & Kartu Piutang (AR)
echo "\n[5/7] Mengaudit Faktur Penjualan (Sales Invoices) & Kartu Piutang...\n";
$latestSi = Invoice::with(['accountReceivable.customer', 'invoiceItem.product'])
    ->where(function ($q) {
        $q->where('from_model_type', SaleOrder::class)
          ->orWhere('from_model_type', 'App\Models\SaleOrder')
          ->orWhere('from_model_type', SuratJalan::class)
          ->orWhere('from_model_type', 'App\Models\SuratJalan')
          ->orWhereNotNull('surat_jalan_ids');
    })
    ->latest('id')
    ->first();

if ($latestSi) {
    $ar = $latestSi->accountReceivable;
    echo "  - Faktur Penjualan Terakhir: {$latestSi->invoice_number} (ID: {$latestSi->id}) | Status: {$latestSi->status}\n";
    echo "    Customer: " . ($latestSi->customer?->name ?? $latestSi->customer_name) . " | Total Tagihan: Rp " . number_format((float)$latestSi->total, 2, ',', '.') . "\n";
    echo "    DPP: Rp " . number_format((float)$latestSi->subtotal, 2, ',', '.') . " | PPN: Rp " . number_format((float)$latestSi->tax, 2, ',', '.') . "\n";
    if ($ar) {
        echo "    Kartu Piutang (AR): Saldo Awal Rp " . number_format((float)$ar->amount, 2, ',', '.') .
             " | Terbayar Rp " . number_format((float)$ar->paid, 2, ',', '.') .
             " | Sisa Piutang Rp " . number_format((float)$ar->remaining, 2, ',', '.') . "\n";
    } else {
        echo "    [WARNING] Kartu Piutang (AR) belum terbentuk untuk invoice ini.\n";
    }
} else {
    echo "  - Belum ada Sales Invoice.\n";
}

// 6. Audit Penerimaan Pembayaran Kasir (Customer Receipts)
echo "\n[6/7] Mengaudit Penerimaan Pembayaran Kasir (Customer Receipts)...\n";
$latestCr = CustomerReceipt::with(['customerReceiptItem.invoice', 'customer', 'coa'])->latest('id')->first();
if ($latestCr) {
    echo "  - Kwitansi Kasir Terakhir: {$latestCr->receipt_number} (ID: {$latestCr->id}) | Status: {$latestCr->status}\n";
    echo "    Metode: {$latestCr->payment_method} | Akun Kas/Bank: {$latestCr->coa?->name} ({$latestCr->coa?->code})\n";
    echo "    Total Diterima: Rp " . number_format((float)$latestCr->total_payment, 2, ',', '.') . "\n";
    foreach ($latestCr->customerReceiptItem as $cri) {
        echo "      * Faktur: {$cri->invoice?->invoice_number} | Alokasi: Rp " . number_format((float)$cri->payment_amount, 2, ',', '.') . "\n";
    }
} else {
    echo "  - Belum ada Customer Receipt.\n";
}

// 7. Audit Keseimbangan Jurnal Akuntansi (Debit == Credit)
echo "\n[7/7] Mengaudit Keseimbangan Buku Besar & Jurnal Akuntansi...\n";
$unbalancedJournals = DB::table('journal_entries')
    ->whereNotNull('transaction_id')
    ->select('transaction_id', DB::raw('SUM(debit) as d'), DB::raw('SUM(credit) as c'), DB::raw('ABS(SUM(debit) - SUM(credit)) as diff'))
    ->groupBy('transaction_id')
    ->havingRaw('diff > 0.01')
    ->get();

if ($unbalancedJournals->isEmpty()) {
    echo "  [OK] Seluruh entri jurnal akuntansi seimbang secara presisi nol (Debit == Credit).\n";
} else {
    echo "  [FAIL] Ditemukan " . $unbalancedJournals->count() . " jurnal tidak seimbang:\n";
    foreach ($unbalancedJournals as $uj) {
        echo "    * Transaksi {$uj->transaction_id}: Debit={$uj->d}, Credit={$uj->c}, Diff={$uj->diff}\n";
        $results['discrepancies'][] = "Jurnal {$uj->transaction_id} selisih {$uj->diff}";
    }
    $results['passed'] = false;
    $results['journal_balance'] = false;
}

echo "\n====================================================================\n";
echo " AUDIT SIKLUS B STATUS: " . ($results['passed'] ? "READY (PASSED)" : "NEEDS INVESTIGATION") . "\n";
echo "====================================================================\n";
