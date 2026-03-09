<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== SALES REPORT VS LEDGER MATCHING TEST ===\n\n";

// Check if we have test data
$salesOrdersCount = \App\Models\SaleOrder::count();
$completedSalesOrdersCount = \App\Models\SaleOrder::where('status', 'completed')->count();
$journalEntriesCount = \App\Models\JournalEntry::count();
$salesJournalEntriesCount = \App\Models\JournalEntry::where('journal_type', 'sales')->count();

echo "CURRENT DATABASE STATE:\n";
echo "- Total Sales Orders: $salesOrdersCount\n";
echo "- Completed Sales Orders: $completedSalesOrdersCount\n";
echo "- Total Journal Entries: $journalEntriesCount\n";
echo "- Sales Journal Entries: $salesJournalEntriesCount\n\n";

if ($completedSalesOrdersCount == 0) {
    echo "NO COMPLETED SALES ORDERS FOUND. CREATING TEST DATA...\n\n";

    // Create test cabang
    $cabang = \App\Models\Cabang::firstOrCreate(
        ['kode' => 'CAB001'],
        [
            'nama' => 'Test Cabang',
        ]
    );

    // Create test warehouse
    $warehouse = \App\Models\Warehouse::firstOrCreate(
        ['kode' => 'WH001'],
        [
            'name' => 'Test Warehouse',
            'cabang_id' => $cabang->id,
        ]
    );

    // Create test customer
    $customer = \App\Models\Customer::firstOrCreate(
        ['code' => 'CUST001'],
        [
            'name' => 'Test Customer',
            'perusahaan' => 'Test Company',
            'cabang_id' => $cabang->id,
        ]
    );

    // Create test product
    $product = \App\Models\Product::firstOrCreate(
        ['sku' => 'PROD001'],
        [
            'name' => 'Test Product',
            'sell_price' => 100000,
            'cost_price' => 80000,
            'pajak' => 11, // 11% tax
            'tipe_pajak' => 'Inklusif',
            'cabang_id' => $cabang->id,
        ]
    );

    // Create sales order
    $saleOrder = \App\Models\SaleOrder::factory()->create([
        'so_number' => 'SO-TEST-001',
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'status' => 'confirmed',
        'total_amount' => 111000, // 100000 + 11% tax
    ]);

    // Create sales order item
    \App\Models\SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 11,
        'warehouse_id' => $warehouse->id,
    ]);

    // Create delivery order
    $deliveryOrder = \App\Models\DeliveryOrder::factory()->create([
        'do_number' => 'DO-TEST-001',
        'sale_order_id' => $saleOrder->id,
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'warehouse_id' => $warehouse->id,
        'status' => 'confirmed',
    ]);

    // Create delivery order item
    \App\Models\DeliveryOrderItem::factory()->create([
        'delivery_order_id' => $deliveryOrder->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100000,
    ]);

    // Update delivery order status to completed
    $deliveryOrder->update(['status' => 'completed']);

    // Now update sales order to completed (this should trigger invoice creation)
    $saleOrder->update(['status' => 'completed']);

    echo "TEST DATA CREATED:\n";
    echo "- Customer: {$customer->name} ({$customer->code})\n";
    echo "- Product: {$product->name} ({$product->sku})\n";
    echo "- Sales Order: {$saleOrder->so_number}\n";
    echo "- Delivery Order: {$deliveryOrder->do_number}\n\n";

    // Refresh counts
    $salesOrdersCount = \App\Models\SaleOrder::count();
    $completedSalesOrdersCount = \App\Models\SaleOrder::where('status', 'completed')->count();
    $journalEntriesCount = \App\Models\JournalEntry::count();
    $salesJournalEntriesCount = \App\Models\JournalEntry::where('journal_type', 'sales')->count();

    echo "UPDATED DATABASE STATE:\n";
    echo "- Total Sales Orders: $salesOrdersCount\n";
    echo "- Completed Sales Orders: $completedSalesOrdersCount\n";
    echo "- Total Journal Entries: $journalEntriesCount\n";
    echo "- Sales Journal Entries: $salesJournalEntriesCount\n\n";
}

// Now perform the matching test
$startDate = '2024-01-01';
$endDate = '2024-12-31';

echo "=== SALES REPORT ANALYSIS ===\n";
$salesOrders = \App\Models\SaleOrder::whereBetween('created_at', [$startDate, $endDate])
    ->where('status', 'completed')
    ->with(['customer', 'saleOrderItem'])
    ->get();

$salesReportTotal = $salesOrders->sum('total_amount');
echo "Period: {$startDate} to {$endDate}\n";
echo "Total Completed Sales Orders: " . $salesOrders->count() . "\n";
echo "Total Sales Amount (from SO): Rp " . number_format($salesReportTotal, 0, ',', '.') . "\n\n";

echo "=== LEDGER ANALYSIS ===\n";
$ledgerEntries = \App\Models\JournalEntry::whereBetween('date', [$startDate, $endDate])
    ->where('journal_type', 'sales')
    ->where('source_type', 'App\Models\Invoice')
    ->get();

echo "Total Sales Journal Entries: " . $ledgerEntries->count() . "\n";

// Check AR COA (1120)
$arCoa = \App\Models\ChartOfAccount::where('code', '1120')->first();
if ($arCoa) {
    $arEntries = $ledgerEntries->where('coa_id', $arCoa->id);
    $arDebit = $arEntries->sum('debit');
    $arCredit = $arEntries->sum('credit');
    echo "AR (1120) - Debit: Rp " . number_format($arDebit, 0, ',', '.') . ", Credit: Rp " . number_format($arCredit, 0, ',', '.') . "\n";
} else {
    echo "ERROR: AR COA (1120) not found\n";
    $arDebit = 0;
}

// Check Revenue COA (4000)
$revenueCoa = \App\Models\ChartOfAccount::where('code', '4000')->first();
if ($revenueCoa) {
    $revenueEntries = $ledgerEntries->where('coa_id', $revenueCoa->id);
    $revenueDebit = $revenueEntries->sum('debit');
    $revenueCredit = $revenueEntries->sum('credit');
    echo "Revenue (4000) - Debit: Rp " . number_format($revenueDebit, 0, ',', '.') . ", Credit: Rp " . number_format($revenueCredit, 0, ',', '.') . "\n";
} else {
    echo "ERROR: Revenue COA (4000) not found\n";
}

// Check PPn Keluaran COA (2120.06)
$ppnCoa = \App\Models\ChartOfAccount::where('code', '2120.06')->first();
if ($ppnCoa) {
    $ppnEntries = $ledgerEntries->where('coa_id', $ppnCoa->id);
    $ppnDebit = $ppnEntries->sum('debit');
    $ppnCredit = $ppnEntries->sum('credit');
    echo "PPn Keluaran (2120.06) - Debit: Rp " . number_format($ppnDebit, 0, ',', '.') . ", Credit: Rp " . number_format($ppnCredit, 0, ',', '.') . "\n";
} else {
    echo "ERROR: PPn Keluaran COA (2120.06) not found\n";
}

echo "\n=== COMPARISON ===\n";
echo "Sales Report Total: Rp " . number_format($salesReportTotal, 0, ',', '.') . "\n";
echo "AR Debit (should equal sales total): Rp " . number_format($arDebit, 0, ',', '.') . "\n";
$match = $salesReportTotal == $arDebit;
echo "Match: " . ($match ? 'YES ✓' : 'NO ✗') . "\n";

if (!$match) {
    $difference = abs($salesReportTotal - $arDebit);
    echo "Difference: Rp " . number_format($difference, 0, ',', '.') . "\n";
}

echo "\n=== DETAILED INVOICE BREAKDOWN ===\n";
$invoices = \App\Models\Invoice::whereBetween('invoice_date', [$startDate, $endDate])
    ->where('from_model_type', 'App\Models\SaleOrder')
    ->with(['fromModel'])
    ->get();

foreach ($invoices as $invoice) {
    echo "Invoice: {$invoice->invoice_number}\n";
    echo "  - Invoice Total: Rp " . number_format($invoice->total, 0, ',', '.') . "\n";
    echo "  - Invoice Subtotal: Rp " . number_format($invoice->subtotal, 0, ',', '.') . "\n";
    echo "  - Invoice Tax: Rp " . number_format($invoice->tax, 0, ',', '.') . "\n";

    $saleOrder = $invoice->fromModel;
    if ($saleOrder) {
        echo "  - SO Number: {$saleOrder->so_number}\n";
        echo "  - SO Total: Rp " . number_format($saleOrder->total_amount, 0, ',', '.') . "\n";
    }

    // Check journal entries for this invoice
    $invoiceJournals = \App\Models\JournalEntry::where('source_type', 'App\Models\Invoice')
        ->where('source_id', $invoice->id)
        ->get();

    echo "  - Posted to Ledger: " . ($invoiceJournals->isNotEmpty() ? 'YES' : 'NO') . "\n";

    if ($invoiceJournals->isNotEmpty()) {
        echo "  - Journal Entries:\n";
        foreach ($invoiceJournals as $je) {
            $coa = \App\Models\ChartOfAccount::find($je->coa_id);
            $coaName = $coa ? $coa->name : 'Unknown';
            echo "    * {$coaName} ({$je->coa_id}): Debit Rp " . number_format($je->debit, 0, ',', '.') . ", Credit Rp " . number_format($je->credit, 0, ',', '.') . "\n";
        }
    }
    echo "\n";
}

echo "=== FINAL RESULT ===\n";
echo "Sales Report vs Ledger AR Matching: " . ($match ? 'PASS ✓' : 'FAIL ✗') . "\n";

if (!$match) {
    echo "\nPOSSIBLE ISSUES:\n";
    echo "1. Some invoices may not be posted to ledger\n";
    echo "2. Date mismatch between SO creation and invoice date\n";
    echo "3. Missing COA mappings\n";
    echo "4. Observer not triggered properly\n";
}