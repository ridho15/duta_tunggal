<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;

// Find the PPN Masukan COA
$ppnCoa = ChartOfAccount::where('code', '1170.06')->first();
if (!$ppnCoa) {
    $ppnCoa = ChartOfAccount::where('name', 'like', '%PPN MASUKAN%')->first();
}

if (!$ppnCoa) {
    echo "PPN Masukan COA not found!\n";
    exit(1);
}

echo "COA: {$ppnCoa->id} - {$ppnCoa->code} - {$ppnCoa->name}\n\n";

$entries = JournalEntry::where('coa_id', $ppnCoa->id)
    ->where('debit', '>', 0)
    ->orderBy('date')
    ->get(['id', 'date', 'transaction_id', 'debit', 'credit', 'description', 'source_type', 'source_id', 'cabang_id']);

echo "PPN Masukan DEBIT entries:\n";
foreach ($entries as $e) {
    echo "  id={$e->id} | date={$e->date} | txn_id=" . ($e->transaction_id ?? 'NULL') 
        . " | debit={$e->debit} | credit={$e->credit} | desc={$e->description}"
        . " | source={$e->source_type}#{$e->source_id} | cabang={$e->cabang_id}\n";
    
    // Check if this transaction is balanced
    if ($e->transaction_id) {
        $txnDebit  = JournalEntry::where('transaction_id', $e->transaction_id)->sum('debit');
        $txnCredit = JournalEntry::where('transaction_id', $e->transaction_id)->sum('credit');
        echo "    Transaction total: debit={$txnDebit} credit={$txnCredit} diff=" . ($txnDebit - $txnCredit) . "\n";
    } else {
        // Null transaction_id - check by source_type + source_id
        if ($e->source_type && $e->source_id) {
            $srcDebit  = JournalEntry::where('source_type', $e->source_type)->where('source_id', $e->source_id)->sum('debit');
            $srcCredit = JournalEntry::where('source_type', $e->source_type)->where('source_id', $e->source_id)->sum('credit');
            echo "    Source-based total: debit={$srcDebit} credit={$srcCredit} diff=" . ($srcDebit - $srcCredit) . "\n";
            
            // Show all entries for this source
            $srcEntries = JournalEntry::where('source_type', $e->source_type)
                ->where('source_id', $e->source_id)
                ->join('chart_of_accounts', 'journal_entries.coa_id', '=', 'chart_of_accounts.id')
                ->select('journal_entries.*', 'chart_of_accounts.code as coa_code', 'chart_of_accounts.name as coa_name')
                ->get();
            foreach ($srcEntries as $se) {
                echo "      COA: {$se->coa_code} - {$se->coa_name} | d={$se->debit} | cr={$se->credit}\n";
            }
        }
    }
}
