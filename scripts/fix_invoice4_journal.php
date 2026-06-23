<?php
/**
 * Fix unbalanced journal entry for Invoice#4 (PINV-20260312-001).
 *
 * Root cause: The AP (Hutang Dagang) credit was posted as subtotal (500,000)
 * instead of subtotal + PPN = 555,000. This caused a 55,000 imbalance in the balance sheet.
 *
 * Fix: Update the existing AP credit entry (id=10) from 500,000 → 555,000.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

// Verify the current state
echo "=== BEFORE FIX ===\n";
$apEntry = JournalEntry::find(10);
if (!$apEntry) {
    echo "Journal entry id=10 not found!\n";
    exit(1);
}

$apCoa = ChartOfAccount::find($apEntry->coa_id);
echo "JE id=10: {$apCoa->code} - {$apCoa->name} | debit={$apEntry->debit} | credit={$apEntry->credit}\n";

// Verify the imbalance
$totalDebit  = (float) JournalEntry::sum('debit');
$totalCredit = (float) JournalEntry::sum('credit');
echo "Global: total_debit={$totalDebit}, total_credit={$totalCredit}, diff=" . ($totalDebit - $totalCredit) . "\n";

// Verify we're updating the right entry
if ($apCoa->code !== '2110') {
    echo "ERROR: JE id=10 is not the HUTANG DAGANG account (code 2110)! Found: {$apCoa->code}\n";
    exit(1);
}
if ($apEntry->credit != 500000) {
    echo "ERROR: JE id=10 credit is not 500000 (found: {$apEntry->credit}). Skipping fix.\n";
    exit(1);
}

// Apply fix
DB::beginTransaction();
try {
    $apEntry->credit = 555000;
    $apEntry->description = $apEntry->description . ' [CORRECTED: PPN 55,000 added to AP]';
    $apEntry->save();

    echo "\n=== AFTER FIX ===\n";
    $apEntry->refresh();
    echo "JE id=10: {$apCoa->code} - {$apCoa->name} | debit={$apEntry->debit} | credit={$apEntry->credit}\n";

    $totalDebit2  = (float) JournalEntry::sum('debit');
    $totalCredit2 = (float) JournalEntry::sum('credit');
    echo "Global: total_debit={$totalDebit2}, total_credit={$totalCredit2}, diff=" . ($totalDebit2 - $totalCredit2) . "\n";

    if (abs($totalDebit2 - $totalCredit2) < 0.01) {
        echo "\nJOURNAL ENTRIES ARE NOW BALANCED ✓\n";
        DB::commit();
    } else {
        echo "\nWARNING: Still unbalanced after fix! diff=" . ($totalDebit2 - $totalCredit2) . "\n";
        echo "Rolling back...\n";
        DB::rollBack();
        exit(1);
    }
} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Also fix the Invoice#4 total field to include PPN
echo "\n=== FIXING INVOICE#4 TOTAL ===\n";
$inv = App\Models\Invoice::find(4);
echo "Invoice#4 before: total={$inv->total}, subtotal={$inv->subtotal}, ppn_rate={$inv->ppn_rate}\n";
if ($inv->total == 500000) {
    $inv->total = 555000;
    $inv->save();
    echo "Invoice#4 after: total={$inv->total}\n";
} else {
    echo "Invoice#4 total is not 500000 (found: {$inv->total}), skipping total fix\n";
}
