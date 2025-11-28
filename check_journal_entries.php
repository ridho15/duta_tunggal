<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\JournalEntry;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "🔍 Checking Journal Entries Created During Sales Flow\n";
echo "===================================================\n\n";

// Get all journal entries created in the last few minutes (during test execution)
$recentJournals = JournalEntry::where('created_at', '>=', now()->subMinutes(10))
    ->orderBy('created_at', 'asc')
    ->get();

if ($recentJournals->isEmpty()) {
    echo "❌ No recent journal entries found.\n";
    echo "   Make sure to run the CompleteSalesFlowFilamentTest first.\n\n";
    exit(1);
}

echo "📊 Found " . $recentJournals->count() . " journal entries created recently:\n\n";

echo sprintf("%-3s | %-12s | %-15s | %-10s | %-10s | %-8s | %s\n",
    "No", "Date", "COA Code-Name", "Debit", "Credit", "Type", "Description");
echo str_repeat("=", 100) . "\n";

$totalDebit = 0;
$totalCredit = 0;
$counter = 1;

foreach ($recentJournals as $journal) {
    $coa = $journal->chartOfAccount;
    $coaDisplay = $coa ? $coa->code . ' - ' . substr($coa->name, 0, 15) : 'Unknown';

    echo sprintf("%-3d | %-12s | %-15s | %-10s | %-10s | %-8s | %s\n",
        $counter++,
        $journal->date,
        $coaDisplay,
        number_format($journal->debit, 0, ',', '.'),
        number_format($journal->credit, 0, ',', '.'),
        $journal->journal_type,
        substr($journal->description, 0, 30) . (strlen($journal->description) > 30 ? '...' : '')
    );

    $totalDebit += $journal->debit;
    $totalCredit += $journal->credit;
}

echo str_repeat("=", 100) . "\n";
echo sprintf("%-3s | %-12s | %-15s | %-10s | %-10s | %-8s | %s\n",
    "", "", "TOTAL", number_format($totalDebit, 0, ',', '.'), number_format($totalCredit, 0, ',', '.'), "", "");

echo "\n";

// Group by journal type
$groupedByType = $recentJournals->groupBy('journal_type');

echo "📈 Journal Entries by Type:\n";
echo "--------------------------\n";
foreach ($groupedByType as $type => $entries) {
    echo "🔸 {$type}: {$entries->count()} entries\n";
}

echo "\n";

// Group by source type
$groupedBySource = $recentJournals->groupBy('source_type');

echo "📋 Journal Entries by Source:\n";
echo "-----------------------------\n";
foreach ($groupedBySource as $source => $entries) {
    $sourceName = str_replace('App\\Models\\', '', $source);
    echo "🔸 {$sourceName}: {$entries->count()} entries\n";
}

echo "\n";

// Check balance
if ($totalDebit == $totalCredit) {
    echo "✅ Journal entries are BALANCED! ✓\n";
    echo "   Total Debit: Rp " . number_format($totalDebit, 0, ',', '.') . "\n";
    echo "   Total Credit: Rp " . number_format($totalCredit, 0, ',', '.') . "\n";
} else {
    echo "❌ Journal entries are NOT balanced!\n";
    echo "   Total Debit: Rp " . number_format($totalDebit, 0, ',', '.') . "\n";
    echo "   Total Credit: Rp " . number_format($totalCredit, 0, ',', '.') . "\n";
    echo "   Difference: Rp " . number_format(abs($totalDebit - $totalCredit), 0, ',', '.') . "\n";
}

echo "\n🎯 Accounting Flow Summary:\n";
echo "===========================\n";

// Calculate accounting impact
$salesRevenue = $recentJournals->where('journal_type', 'sales')
    ->where('description', 'like', '%Revenue%')
    ->sum('credit');

$cogsExpense = $recentJournals->where('journal_type', 'sales')
    ->where('description', 'like', '%Goods Delivery%')
    ->sum('debit');

$cashReceived = $recentJournals->where('journal_type', 'Sales')
    ->where('description', 'like', '%receipt%')
    ->sum('debit');

$inventoryReduction = $recentJournals->where('journal_type', 'sales')
    ->where('description', 'like', '%Inventory reduction%')
    ->sum('credit');

echo "💰 Sales Revenue: +Rp " . number_format($salesRevenue, 0, ',', '.') . "\n";
echo "📦 COGS Expense: -Rp " . number_format($cogsExpense, 0, ',', '.') . "\n";
echo "💵 Cash Received: +Rp " . number_format($cashReceived, 0, ',', '.') . "\n";
echo "📦 Inventory Reduced: -Rp " . number_format($inventoryReduction, 0, ',', '.') . "\n";

$netProfit = $salesRevenue - $cogsExpense;
echo "💎 Net Profit: +Rp " . number_format($netProfit, 0, ',', '.') . "\n";

echo "\n🏁 Analysis Complete!\n";