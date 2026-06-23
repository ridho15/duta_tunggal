<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Check fixture PO
$po = DB::table('purchase_orders')->where('po_number', 'PO-TEST-INV-B23')->first();
echo 'PO: ' . ($po ? "id={$po->id} status={$po->status} supplier_id={$po->supplier_id}" : 'NOT FOUND') . PHP_EOL;

if (!$po) {
    echo "Run: php scripts/setup_purchase_invoice_playwright_data.php first\n";
    exit(1);
}

// Check supplier
$supp = DB::table('suppliers')->where('id', $po->supplier_id)->first();
echo 'Supplier: ' . ($supp ? 'id=' . $supp->id . ' name=' . ($supp->perusahaan ?? $supp->name ?? '-') : 'NOT FOUND') . PHP_EOL;

// Check receipts
$receipts = DB::table('purchase_receipts')->where('purchase_order_id', $po->id)->get();
echo 'Receipts (' . count($receipts) . '):' . PHP_EOL;
foreach ($receipts as $r) {
    echo "  id={$r->id} number={$r->receipt_number} status={$r->status}" . PHP_EOL;
}

// Check cabang. Some schemas store cabang only on purchase_receipts, and the
// Purchase Invoice UI filters PO options by receipt cabang.
$cabangId = Schema::hasColumn('purchase_orders', 'cabang_id')
    ? ($po->cabang_id ?? null)
    : null;

if (!$cabangId && Schema::hasColumn('purchase_receipts', 'cabang_id')) {
    $cabangId = $receipts->first()?->cabang_id;
}

$cabang = $cabangId ? DB::table('cabangs')->where('id', $cabangId)->first() : null;
echo 'Cabang: ' . ($cabang ? "id={$cabang->id} kode={$cabang->kode} name={$cabang->nama}" : 'NOT FOUND') . PHP_EOL;

// Check which are invoiced
$allInvoices = DB::table('invoices')->whereNotNull('purchase_receipts')->get();
$invoicedIds = [];
foreach ($allInvoices as $inv) {
    $arr = json_decode($inv->purchase_receipts, true);
    if (is_array($arr)) {
        foreach ($arr as $rid) {
            $invoicedIds[(int)$rid] = $inv->invoice_number;
        }
    }
}

echo PHP_EOL . 'All invoiced receipt IDs across all invoices: ' . json_encode(array_keys($invoicedIds)) . PHP_EOL;
echo PHP_EOL . 'Per-receipt status for PO:' . PHP_EOL;
foreach ($receipts as $r) {
    $disabled = isset($invoicedIds[(int)$r->id]);
    echo "  {$r->receipt_number} (id={$r->id}): " . ($disabled ? "DISABLED (invoiced in {$invoicedIds[(int)$r->id]})" : 'ENABLED') . PHP_EOL;
}

// Also check if supplier has the right kode_supplier
echo PHP_EOL . 'Checking supplier by SUPP001:' . PHP_EOL;
$supp2 = DB::table('suppliers')->where('code', 'SUPP001')->first();
echo $supp2 ? "  Found: id={$supp2->id} name={$supp2->perusahaan}" : "  NOT FOUND by code=SUPP001";
echo PHP_EOL;
