<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== STARTING TWO-WAY DATA INTEGRITY VERIFICATION ===" . PHP_EOL;

// 1. Relational Foreign Key Integrity
$orphanedOr = DB::table('order_request_items')
    ->leftJoin('order_requests', 'order_request_items.order_request_id', '=', 'order_requests.id')
    ->whereNull('order_requests.id')
    ->count();

$orphanedSo = DB::table('sale_order_items')
    ->leftJoin('sale_orders', 'sale_order_items.sale_order_id', '=', 'sale_orders.id')
    ->whereNull('sale_orders.id')
    ->count();

echo "ORPHAN_OR: " . $orphanedOr . PHP_EOL;
echo "ORPHAN_SO: " . $orphanedSo . PHP_EOL;

// 2. Double-Entry Bookkeeping (Journal Balance: SUM(debit) == SUM(credit))
$unbalancedJournals = DB::table('journal_entries')
    ->whereNotNull('transaction_id')
    ->select('transaction_id', DB::raw('SUM(debit) as d'), DB::raw('SUM(credit) as c'))
    ->groupBy('transaction_id')
    ->havingRaw('ROUND(ABS(SUM(debit) - SUM(credit)), 2) > 0.05')
    ->count();

echo "UNBALANCED_JOURNALS: " . $unbalancedJournals . PHP_EOL;

// 3. Soft Delete Schema Support
$hasOrDeletedAt = Schema::hasColumn('order_requests', 'deleted_at');
$hasSoDeletedAt = Schema::hasColumn('sale_orders', 'deleted_at');
$hasPoDeletedAt = Schema::hasColumn('purchase_orders', 'deleted_at');

echo "SOFT_DELETE_OR: " . ($hasOrDeletedAt ? 'YES' : 'NO') . PHP_EOL;
echo "SOFT_DELETE_SO: " . ($hasSoDeletedAt ? 'YES' : 'NO') . PHP_EOL;
echo "SOFT_DELETE_PO: " . ($hasPoDeletedAt ? 'YES' : 'NO') . PHP_EOL;

// 4. Financial Decimal Verification
$invoices = App\Models\Invoice::whereNotNull('total')->get();
echo "TOTAL_INVOICES_CHECKED: " . $invoices->count() . PHP_EOL;

// Check precision on invoices
$unbalancedInvoices = 0;
foreach ($invoices as $inv) {
    $taxRateOrNominal = (float) $inv->tax;
    $taxNominal = $taxRateOrNominal > 100 
        ? $taxRateOrNominal 
        : round((float) $inv->subtotal * ($taxRateOrNominal / 100), 2);
    
    $otherFee = 0.0;
    if (is_numeric($inv->other_fee)) {
        $otherFee = (float) $inv->other_fee;
    } elseif (is_array($inv->other_fee)) {
        foreach ($inv->other_fee as $fee) {
            $otherFee += (float) ($fee['amount'] ?? 0);
        }
    }

    $expectedTotal = round((float) $inv->subtotal + $taxNominal + $otherFee, 2);
    if (abs($expectedTotal - (float) $inv->total) > 0.05) {
        $unbalancedInvoices++;
    }
}

echo "UNBALANCED_INVOICES: " . $unbalancedInvoices . PHP_EOL;

echo "=== INTEGRITY VERIFICATION FINISHED ===" . PHP_EOL;
