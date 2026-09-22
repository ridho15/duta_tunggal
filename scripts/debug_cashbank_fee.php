<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$from = App\Models\ChartOfAccount::firstOrCreate(['code'=>'1111.01'], ['name'=>'Kas Besar','type'=>'asset','level'=>3,'is_active'=>true]);
$to   = App\Models\ChartOfAccount::firstOrCreate(['code'=>'1111.02'], ['name'=>'Kas Kecil','type'=>'asset','level'=>3,'is_active'=>true]);
$num  = 'TEST-DBG-'.time();

$t = App\Models\CashBankTransfer::create([
    'number' => $num, 'date' => now()->toDateString(),
    'from_coa_id' => $from->id, 'to_coa_id' => $to->id,
    'amount' => 1000000, 'other_costs' => 50000,
    'description' => 'dbg', 'status' => 'draft',
]);
echo "Transfer ID: {$t->id}, other_costs: {$t->other_costs}\n";

try {
    app(App\Services\CashBankService::class)->postTransfer($t);
    echo "postTransfer OK\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

$t->refresh();
$entries = App\Models\JournalEntry::withoutGlobalScopes()
    ->where('source_type', App\Models\CashBankTransfer::class)
    ->where('source_id', $t->id)
    ->withTrashed()
    ->get();
echo "Total entries (withoutGlobalScopes+withTrashed): " . $entries->count() . "\n";
foreach ($entries as $e) {
    echo "  id={$e->id} coa={$e->coa_id} debit={$e->debit} credit={$e->credit} journal_type={$e->journal_type} deleted_at={$e->deleted_at}\n";
}
echo "Via relation: " . $t->journalEntries()->count() . "\n";

// Cleanup
App\Models\CashBankTransfer::where('number','like','TEST-DBG-%')->forceDelete();
App\Models\JournalEntry::withoutGlobalScopes()->withTrashed()->where('reference','like','TEST-DBG-%')->forceDelete();
echo "Cleaned up\n";
