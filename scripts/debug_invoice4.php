<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Invoice;
use App\Models\JournalEntry;

$inv = Invoice::find(4);
if (!$inv) { echo "Invoice 4 not found\n"; exit; }

echo "Invoice: {$inv->invoice_number}\n";
echo "  subtotal: {$inv->subtotal}\n";
echo "  tax (rate): {$inv->tax}\n";
echo "  ppn_rate: {$inv->ppn_rate}\n";
echo "  total: {$inv->total}\n";
echo "  total_amount: " . ($inv->total_amount ?? 'N/A') . "\n";
echo "  tipe_pajak: " . ($inv->tipe_pajak ?? 'N/A') . "\n";

// Show journal entries for this invoice
echo "\nJournal Entries for Invoice#4:\n";
$jes = JournalEntry::where('source_type', Invoice::class)->where('source_id', 4)
    ->join('chart_of_accounts', 'journal_entries.coa_id', '=', 'chart_of_accounts.id')
    ->select('journal_entries.*', 'chart_of_accounts.code as coa_code', 'chart_of_accounts.name as coa_name')
    ->get();
foreach ($jes as $je) {
    echo "  id={$je->id} | {$je->coa_code} - {$je->coa_name} | d={$je->debit} | cr={$je->credit}\n";
}
$d = $jes->sum('debit');
$c = $jes->sum('credit');
echo "  Total: debit={$d}, credit={$c}, diff=" . ($d - $c) . "\n";
