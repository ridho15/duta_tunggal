<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BalanceSheetService;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

echo "Balance Sheet Analysis\n";
echo "=====================\n";

// 1. Generate Balance Sheet
$service = new BalanceSheetService();
$balanceSheet = $service->generate([
    'as_of_date' => now()->format('Y-m-d'),
    'display_level' => 'all',
    'show_zero_balance' => true
]);

echo "Balance Sheet Totals:\n";
echo "Assets: " . number_format($balanceSheet['total_assets'], 0, ',', '.') . "\n";
echo "Liabilities: " . number_format($balanceSheet['total_liabilities'], 0, ',', '.') . "\n";
echo "Equity: " . number_format($balanceSheet['total_equity'], 0, ',', '.') . "\n";
echo "Liabilities + Equity: " . number_format($balanceSheet['total_liabilities'] + $balanceSheet['total_equity'], 0, ',', '.') . "\n";
echo "Difference: " . number_format($balanceSheet['total_assets'] - ($balanceSheet['total_liabilities'] + $balanceSheet['total_equity']), 0, ',', '.') . "\n";

if (abs($balanceSheet['total_assets'] - ($balanceSheet['total_liabilities'] + $balanceSheet['total_equity'])) > 0.01) {
    echo "❌ BALANCE SHEET IS NOT BALANCED!\n\n";
} else {
    echo "✅ Balance Sheet is balanced\n\n";
}

// 2. Check Journal Entries Balance
echo "Journal Entries Balance Check:\n";
echo "===============================\n";

$journalEntries = JournalEntry::all()->groupBy('source_type')->groupBy('source_id', true);

$unbalancedTransactions = [];
$totalDebits = 0;
$totalCredits = 0;

foreach ($journalEntries as $sourceType => $sources) {
    foreach ($sources as $sourceId => $entries) {
        $debits = $entries->sum('debit');
        $credits = $entries->sum('credit');
        $totalDebits += $debits;
        $totalCredits += $credits;

        if (abs($debits - $credits) > 0.01) {
            $unbalancedTransactions[] = [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'debits' => $debits,
                'credits' => $credits,
                'difference' => $debits - $credits
            ];
        }
    }
}

echo "Total Journal Debits: " . number_format($totalDebits, 0, ',', '.') . "\n";
echo "Total Journal Credits: " . number_format($totalCredits, 0, ',', '.') . "\n";
echo "Journal Difference: " . number_format($totalDebits - $totalCredits, 0, ',', '.') . "\n";

if (!empty($unbalancedTransactions)) {
    echo "\n❌ Unbalanced Transactions:\n";
    foreach ($unbalancedTransactions as $transaction) {
        echo "  - {$transaction['source_type']} ID {$transaction['source_id']}: " .
             "Debits=" . number_format($transaction['debits'], 0, ',', '.') .
             ", Credits=" . number_format($transaction['credits'], 0, ',', '.') .
             ", Diff=" . number_format($transaction['difference'], 0, ',', '.') . "\n";
    }
} else {
    echo "\n✅ All journal transactions are balanced\n";
}

// 3. Check Recent Journal Entries
echo "\nRecent Journal Entries (last 10):\n";
echo "==================================\n";

$recentEntries = JournalEntry::orderBy('id', 'desc')->take(10)->get();

foreach ($recentEntries as $entry) {
    echo "ID {$entry->id}: {$entry->coa->name} - " .
         "Debit: " . number_format($entry->debit, 0, ',', '.') . ", " .
         "Credit: " . number_format($entry->credit, 0, ',', '.') . " - " .
         "Source: {$entry->source_type} {$entry->source_id} - " .
         "Desc: {$entry->description}\n";
}

// 4. Check COA Balances
echo "\nCOA Balances Summary:\n";
echo "=====================\n";

$coaBalances = DB::table('chart_of_accounts')
    ->leftJoin('journal_entries', 'chart_of_accounts.id', '=', 'journal_entries.coa_id')
    ->select(
        'chart_of_accounts.id',
        'chart_of_accounts.code',
        'chart_of_accounts.name',
        'chart_of_accounts.type',
        'chart_of_accounts.opening_balance',
        DB::raw('COALESCE(SUM(journal_entries.debit), 0) as total_debit'),
        DB::raw('COALESCE(SUM(journal_entries.credit), 0) as total_credit')
    )
    ->where('chart_of_accounts.is_active', true)
    ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.type', 'chart_of_accounts.opening_balance')
    ->get();

$assets = 0;
$liabilities = 0;
$equity = 0;

foreach ($coaBalances as $coa) {
    $opening = (float) $coa->opening_balance;
    $balance = match ($coa->type) {
        'Asset' => $opening + $coa->total_debit - $coa->total_credit,
        'Contra Asset' => $opening - $coa->total_debit + $coa->total_credit,
        'Liability', 'Equity' => $opening - $coa->total_debit + $coa->total_credit,
        default => $opening + $coa->total_debit - $coa->total_credit,
    };

    if (abs($balance) > 0.01) {
        echo "{$coa->code} - {$coa->name}: " . number_format($balance, 0, ',', '.') . "\n";
    }

    switch ($coa->type) {
        case 'Asset':
        case 'Contra Asset':
            $assets += $balance;
            break;
        case 'Liability':
            $liabilities += $balance;
            break;
        case 'Equity':
            $equity += $balance;
            break;
    }
}

echo "\nSummary from COA:\n";
echo "Assets: " . number_format($assets, 0, ',', '.') . "\n";
echo "Liabilities: " . number_format($liabilities, 0, ',', '.') . "\n";
echo "Equity: " . number_format($equity, 0, ',', '.') . "\n";
echo "Liabilities + Equity: " . number_format($liabilities + $equity, 0, ',', '.') . "\n";
echo "Difference: " . number_format($assets - ($liabilities + $equity), 0, ',', '.') . "\n";

echo "\nAnalysis Complete!\n";