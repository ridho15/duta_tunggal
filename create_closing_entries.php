<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;

echo "Creating Closing Entries for Revenue and Expense Accounts\n";
echo "=========================================================\n";

$revenues = App\Models\ChartOfAccount::where('type', 'Revenue')->get();
$expenses = App\Models\ChartOfAccount::where('type', 'Expense')->get();
$equityCoa = App\Models\ChartOfAccount::where('type', 'Equity')->first();

if (!$equityCoa) {
    echo "❌ No equity COA found\n";
    exit;
}

echo "Equity COA: {$equityCoa->name}\n\n";

$totalRevenue = 0;
$totalExpense = 0;

// Calculate revenue balances
echo "Revenue Accounts:\n";
foreach ($revenues as $coa) {
    $entries = App\Models\JournalEntry::where('coa_id', $coa->id)->get();
    $balance = ($coa->opening_balance ?? 0) + $entries->sum('debit') - $entries->sum('credit');
    $totalRevenue += $balance;

    if (abs($balance) > 0.01) {
        echo "  {$coa->code} - {$coa->name}: " . number_format($balance, 0, ',', '.') . "\n";
    }
}

// Calculate expense balances
echo "\nExpense Accounts:\n";
foreach ($expenses as $coa) {
    $entries = App\Models\JournalEntry::where('coa_id', $coa->id)->get();
    $balance = ($coa->opening_balance ?? 0) + $entries->sum('debit') - $entries->sum('credit');
    $totalExpense += $balance;

    if (abs($balance) > 0.01) {
        echo "  {$coa->code} - {$coa->name}: " . number_format($balance, 0, ',', '.') . "\n";
    }
}

$netIncome = $totalRevenue - $totalExpense;
echo "\nTotal Revenue Balance: " . number_format($totalRevenue, 0, ',', '.') . "\n";
echo "Total Expense Balance: " . number_format($totalExpense, 0, ',', '.') . "\n";
echo "Net Income/Loss: " . number_format($netIncome, 0, ',', '.') . "\n\n";

if (abs($netIncome) < 0.01) {
    echo "✅ No closing entries needed - accounts already closed\n";
    exit;
}

$closingDate = Carbon::now()->format('Y-m-d');
$reference = 'CLOSE-' . date('YmdHis');

// Close revenue accounts
echo "Creating closing entries for revenue accounts...\n";
foreach ($revenues as $coa) {
    $entries = App\Models\JournalEntry::where('coa_id', $coa->id)->get();
    $balance = ($coa->opening_balance ?? 0) + $entries->sum('debit') - $entries->sum('credit');

    if (abs($balance) > 0.01) {
        // For revenue accounts: debit to close credit balance, credit to close debit balance
        App\Models\JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => $closingDate,
            'reference' => $reference,
            'description' => 'Closing revenue account - ' . $coa->name,
            'debit' => $balance < 0 ? abs($balance) : 0,  // Debit to close credit balance
            'credit' => $balance > 0 ? $balance : 0,      // Credit to close debit balance
            'journal_type' => 'closing',
            'source_type' => 'System',
            'source_id' => 0,
        ]);

        // Opposite entry to equity
        App\Models\JournalEntry::create([
            'coa_id' => $equityCoa->id,
            'date' => $closingDate,
            'reference' => $reference,
            'description' => 'Closing revenue to retained earnings - ' . $coa->name,
            'debit' => $balance > 0 ? $balance : 0,      // Debit equity for revenue loss
            'credit' => $balance < 0 ? abs($balance) : 0, // Credit equity for revenue gain
            'journal_type' => 'closing',
            'source_type' => 'System',
            'source_id' => 0,
        ]);

        echo "  ✅ Closed {$coa->code} - {$coa->name}\n";
    }
}

// Close expense accounts
echo "\nCreating closing entries for expense accounts...\n";
foreach ($expenses as $coa) {
    $entries = App\Models\JournalEntry::where('coa_id', $coa->id)->get();
    $balance = ($coa->opening_balance ?? 0) + $entries->sum('debit') - $entries->sum('credit');

    if (abs($balance) > 0.01) {
        // For expense accounts: credit to close debit balance, debit to close credit balance
        App\Models\JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => $closingDate,
            'reference' => $reference,
            'description' => 'Closing expense account - ' . $coa->name,
            'debit' => $balance < 0 ? abs($balance) : 0, // Debit to close credit balance
            'credit' => $balance > 0 ? $balance : 0,     // Credit to close debit balance
            'journal_type' => 'closing',
            'source_type' => 'System',
            'source_id' => 0,
        ]);

        // Opposite entry to equity
        App\Models\JournalEntry::create([
            'coa_id' => $equityCoa->id,
            'date' => $closingDate,
            'reference' => $reference,
            'description' => 'Closing expense to retained earnings - ' . $coa->name,
            'debit' => $balance > 0 ? $balance : 0,     // Debit equity for expense (reduce retained earnings)
            'credit' => $balance < 0 ? abs($balance) : 0, // Credit equity for expense reduction
            'journal_type' => 'closing',
            'source_type' => 'System',
            'source_id' => 0,
        ]);

        echo "  ✅ Closed {$coa->code} - {$coa->name}\n";
    }
}

echo "\n✅ Closing entries created successfully!\n";
echo "Net Income/Loss of " . number_format($netIncome, 0, ',', '.') . " has been closed to retained earnings.\n";