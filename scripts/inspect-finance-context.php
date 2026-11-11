<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ChartOfAccount;
use App\Models\CashBankAccount;
use App\Models\Asset;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\Cabang;

echo "COA count: " . ChartOfAccount::count() . PHP_EOL;
echo "CashBank Accounts: " . CashBankAccount::count() . PHP_EOL;
echo "Assets count: " . Asset::count() . PHP_EOL;
echo "Accounting Periods: " . AccountingPeriod::count() . PHP_EOL;
echo "Cabang list:" . PHP_EOL;
foreach (Cabang::all() as $cb) {
    echo "  ID {$cb->id}: {$cb->nama} ({$cb->kode})\n";
}

echo "\nLeaf Cash/Bank COAs (whereDoesntHave children):\n";
$leafCash = ChartOfAccount::where(function ($q) {
    $q->where('code', 'like', '1111%')->orWhere('code', 'like', '1112%');
})->whereDoesntHave('children')->get();
foreach ($leafCash as $c) {
    echo "  ID {$c->id}: {$c->code} - {$c->name}\n";
}

echo "\nLeaf Expense COAs:\n";
$leafExp = ChartOfAccount::where('type', 'Expense')->whereDoesntHave('children')->limit(10)->get();
foreach ($leafExp as $c) {
    echo "  ID {$c->id}: {$c->code} - {$c->name}\n";
}


echo "\nAsset COAs:\n";
$assetCoas = ChartOfAccount::where('code', 'like', '1210%')->orWhere('type', 'fixed_asset')->get();
foreach ($assetCoas as $c) {
    echo "  ID {$c->id}: {$c->code} - {$c->name}\n";
}

echo "\nAccumulated Depreciation COAs:\n";
$accumCoas = ChartOfAccount::where('code', 'like', '1220%')->get();
foreach ($accumCoas as $c) {
    echo "  ID {$c->id}: {$c->code} - {$c->name}\n";
}

echo "\nDepreciation Expense COAs:\n";
$expCoas = ChartOfAccount::where('code', 'like', '631%')->get();
foreach ($expCoas as $c) {
    echo "  ID {$c->id}: {$c->code} - {$c->name}\n";
}

echo "\nAll JV- entries in journal_entries:\n";
$allJv = JournalEntry::where('reference', 'like', 'JV-%')->get();
echo "Found " . $allJv->count() . " entries with JV-:\n";
foreach ($allJv as $e) {
    echo "  ID: {$e->id} | Ref: {$e->reference} | Date: {$e->date} | Created: {$e->created_at}\n";
}


