<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$startDate = '2024-01-01';
$endDate = '2024-12-31';

echo "=== SALES REPORT VS LEDGER MATCHING TEST ===\n";
echo "Period: {$startDate} to {$endDate}\n\n";

// Get sales report data
$salesOrders = \App\Models\SaleOrder::whereBetween('created_at', [$startDate, $endDate])
    ->where('status', 'completed')
    ->with(['customer', 'saleOrderItem'])
    ->get();

$salesReportTotal = $salesOrders->sum('total_amount');

echo "=== SALES REPORT ANALYSIS ===\n";
echo "Total Sales Orders: " . $salesOrders->count() . "\n";
echo "Total Sales Amount: Rp " . number_format($salesReportTotal, 0, ',', '.') . "\n\n";

// Get ledger entries for sales
$ledgerEntries = \App\Models\JournalEntry::whereBetween('date', [$startDate, $endDate])
    ->where('journal_type', 'sales')
    ->where('source_type', 'App\Models\Invoice')
    ->get();

$ledgerDebit = $ledgerEntries->where('debit', '>', 0)->sum('debit');
$ledgerCredit = $ledgerEntries->where('credit', '>', 0)->sum('credit');

echo "=== LEDGER ANALYSIS ===\n";
echo "Total Journal Entries: " . $ledgerEntries->count() . "\n";
echo "Total Debit: Rp " . number_format($ledgerDebit, 0, ',', '.') . "\n";
echo "Total Credit: Rp " . number_format($ledgerCredit, 0, ',', '.') . "\n";
echo "Balance: Rp " . number_format($ledgerDebit - $ledgerCredit, 0, ',', '.') . "\n\n";

// Check specific COAs
$arCoa = \App\Models\ChartOfAccount::where('code', '1120')->first();
$revenueCoa = \App\Models\ChartOfAccount::where('code', '4000')->first();

if ($arCoa) {
    $arEntries = $ledgerEntries->where('coa_id', $arCoa->id);
    $arDebit = $arEntries->sum('debit');
    $arCredit = $arEntries->sum('credit');
    echo "AR (1120) - Debit: Rp " . number_format($arDebit, 0, ',', '.') . ", Credit: Rp " . number_format($arCredit, 0, ',', '.') . "\n";
}

if ($revenueCoa) {
    $revenueEntries = $ledgerEntries->where('coa_id', $revenueCoa->id);
    $revenueDebit = $revenueEntries->sum('debit');
    $revenueCredit = $revenueEntries->sum('credit');
    echo "Revenue (4000) - Debit: Rp " . number_format($revenueDebit, 0, ',', '.') . ", Credit: Rp " . number_format($revenueCredit, 0, ',', '.') . "\n";
}

// Check PPn Keluaran
$ppnKeluaranCoa = \App\Models\ChartOfAccount::where('code', '2120.06')->first();
if ($ppnKeluaranCoa) {
    $ppnEntries = $ledgerEntries->where('coa_id', $ppnKeluaranCoa->id);
    $ppnDebit = $ppnEntries->sum('debit');
    $ppnCredit = $ppnEntries->sum('credit');
    echo "PPn Keluaran (2120.06) - Debit: Rp " . number_format($ppnDebit, 0, ',', '.') . ", Credit: Rp " . number_format($ppnCredit, 0, ',', '.') . "\n";
}

echo "\n=== COMPARISON ===\n";
echo "Sales Report Total: Rp " . number_format($salesReportTotal, 0, ',', '.') . "\n";
echo "AR Debit (should match sales total): Rp " . number_format($arDebit ?? 0, 0, ',', '.') . "\n";
echo "Match: " . (($salesReportTotal == ($arDebit ?? 0)) ? 'YES' : 'NO') . "\n\n";

// Detailed breakdown by invoice
echo "=== DETAILED BREAKDOWN BY INVOICE ===\n";
$invoices = \App\Models\Invoice::whereBetween('invoice_date', [$startDate, $endDate])
    ->where('from_model_type', 'App\Models\SaleOrder')
    ->with(['fromModel'])
    ->get();

foreach ($invoices as $invoice) {
    $saleOrder = $invoice->fromModel;
    if ($saleOrder) {
        echo "Invoice: {$invoice->invoice_number}\n";
        echo "  SO Number: {$saleOrder->so_number}\n";
        echo "  SO Total: Rp " . number_format($saleOrder->total_amount, 0, ',', '.') . "\n";
        echo "  Invoice Total: Rp " . number_format($invoice->total, 0, ',', '.') . "\n";

        // Check if posted to ledger
        $journalEntries = \App\Models\JournalEntry::where('source_type', 'App\Models\Invoice')
            ->where('source_id', $invoice->id)
            ->get();

        $posted = $journalEntries->isNotEmpty();
        echo "  Posted to Ledger: " . ($posted ? 'YES' : 'NO') . "\n";

        if ($posted) {
            $arEntry = $journalEntries->where('coa_id', $arCoa->id ?? 0)->first();
            if ($arEntry) {
                echo "  AR Debit: Rp " . number_format($arEntry->debit, 0, ',', '.') . "\n";
            }
        }
        echo "\n";
    }
}

echo "=== SUMMARY ===\n";
$match = $salesReportTotal == ($arDebit ?? 0);
echo "Sales Report vs Ledger Match: " . ($match ? 'PASS ✓' : 'FAIL ✗') . "\n";

if (!$match) {
    $difference = abs($salesReportTotal - ($arDebit ?? 0));
    echo "Difference: Rp " . number_format($difference, 0, ',', '.') . "\n";

    // Check for unposted invoices
    $postedInvoices = \App\Models\JournalEntry::where('source_type', 'App\Models\Invoice')
        ->whereBetween('date', [$startDate, $endDate])
        ->distinct('source_id')
        ->pluck('source_id');

    $unpostedInvoices = $invoices->whereNotIn('id', $postedInvoices);
    echo "Unposted Invoices: " . $unpostedInvoices->count() . "\n";

    if ($unpostedInvoices->count() > 0) {
        echo "Unposted Invoice Details:\n";
        foreach ($unpostedInvoices as $inv) {
            echo "  - {$inv->invoice_number}: Rp " . number_format($inv->total, 0, ',', '.') . "\n";
        }
    }
}