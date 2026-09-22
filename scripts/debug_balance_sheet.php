<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;

// Contra Asset accounts
$contra = ChartOfAccount::where('type', 'Contra Asset')
    ->where('is_active', true)
    ->get(['id', 'code', 'name', 'opening_balance']);

echo "=== CONTRA ASSET ACCOUNTS ===\n";
foreach ($contra as $row) {
    $d  = (float) JournalEntry::where('coa_id', $row->id)->sum('debit');
    $cr = (float) JournalEntry::where('coa_id', $row->id)->sum('credit');
    $netActivity = $d - $cr;
    $sign = -1; // Contra Asset sign
    $balance = $row->opening_balance + ($netActivity * $sign);
    echo $row->code . ' - ' . $row->name
        . ' | ob=' . $row->opening_balance
        . ' | debit=' . $d
        . ' | credit=' . $cr
        . ' | calcBalance=' . $balance . "\n";
}

echo "\n=== OPENING BALANCE SUMMARY ===\n";
echo 'Asset opening:       ' . ChartOfAccount::where('type', 'Asset')->sum('opening_balance') . "\n";
echo 'ContraAsset opening: ' . ChartOfAccount::where('type', 'Contra Asset')->sum('opening_balance') . "\n";
echo 'Liability opening:   ' . ChartOfAccount::where('type', 'Liability')->sum('opening_balance') . "\n";
echo 'Equity opening:      ' . ChartOfAccount::where('type', 'Equity')->sum('opening_balance') . "\n";

echo "\n=== ASSET ACCOUNTS WITH NON-ZERO BALANCE ===\n";
$asOf2 = now();
$allAssets2 = ChartOfAccount::where('type', 'Asset')->where('is_active', true)->get();
foreach ($allAssets2 as $a) {
    $bal = calcBalance($a, $asOf2);
    if (abs($bal) > 0.01) {
        $d  = (float) JournalEntry::where('coa_id', $a->id)->sum('debit');
        $cr = (float) JournalEntry::where('coa_id', $a->id)->sum('credit');
        echo $a->code . ' - ' . $a->name . ' | ob=' . $a->opening_balance . ' | d=' . $d . ' | cr=' . $cr . ' | bal=' . $bal . "\n";
    }
}

echo "\n=== CHECKING GLOBAL JOURNAL BALANCE ===\n";
$totalDebit  = (float) JournalEntry::sum('debit');
$totalCredit = (float) JournalEntry::sum('credit');
echo "Total Debit  in journal_entries: {$totalDebit}\n";
echo "Total Credit in journal_entries: {$totalCredit}\n";
echo "Diff (debit - credit): " . ($totalDebit - $totalCredit) . "\n";
echo "Note: If diff != 0, there are unbalanced JE transactions\n";

echo "\n=== UNBALANCED TRANSACTIONS ===\n";
$unbalanced = JournalEntry::select('transaction_id',
    \DB::raw('SUM(debit) as total_debit'),
    \DB::raw('SUM(credit) as total_credit'),
    \DB::raw('SUM(debit) - SUM(credit) as diff')
)->groupBy('transaction_id')
 ->havingRaw('ABS(SUM(debit) - SUM(credit)) > 0.01')
 ->get();
echo "Found " . $unbalanced->count() . " unbalanced transaction(s)\n";
foreach ($unbalanced as $u) {
    echo "  txn={$u->transaction_id} debit={$u->total_debit} credit={$u->total_credit} diff={$u->diff}\n";
}


// Current (wrong) calculation
$assets = ChartOfAccount::whereIn('type', ['Asset', 'Contra Asset'])->where('is_active', true)->get();
$liabilities = ChartOfAccount::where('type', 'Liability')->where('is_active', true)->get();
$equities = ChartOfAccount::where('type', 'Equity')->where('is_active', true)->get();

$asOf = now();

function calcBalance(ChartOfAccount $coa, $asOf) {
    $sign = match ($coa->type) {
        'Asset', 'Expense' => 1,
        'Contra Asset', 'Liability', 'Equity', 'Revenue' => -1,
        default => 1,
    };
    $q = JournalEntry::where('coa_id', $coa->id)->where('date', '<=', $asOf);
    $d  = (float) (clone $q)->sum('debit');
    $cr = (float) (clone $q)->sum('credit');
    return $coa->opening_balance + (($d - $cr) * $sign);
}

$assetTotal = $assets->sum(fn($c) => calcBalance($c, $asOf));
$liabTotal  = $liabilities->sum(fn($c) => calcBalance($c, $asOf));
$equityAccountsTotal = $equities->sum(fn($c) => calcBalance($c, $asOf));

// Retained earnings (simplified)
$revenue = ChartOfAccount::where('type', 'Revenue')->pluck('id');
$expense = ChartOfAccount::where('type', 'Expense')->pluck('id');
$rev = (float) JournalEntry::whereIn('coa_id', $revenue)->sum('credit')
     - (float) JournalEntry::whereIn('coa_id', $revenue)->sum('debit');
$exp = (float) JournalEntry::whereIn('coa_id', $expense)->sum('debit')
     - (float) JournalEntry::whereIn('coa_id', $expense)->sum('credit');

$openingAssets     = ChartOfAccount::whereIn('type', ['Asset', 'Contra Asset'])->sum('opening_balance');
$openingLiab       = ChartOfAccount::where('type', 'Liability')->sum('opening_balance');
$openingEquity     = ChartOfAccount::where('type', 'Equity')->sum('opening_balance');
$openingImbalance  = $openingAssets - $openingLiab - $openingEquity;
$retainedEarnings  = ($rev - $exp) - $openingImbalance;

$equityTotal = $equityAccountsTotal + $retainedEarnings;
$liabEquityTotal = $liabTotal + $equityTotal;

echo "CURRENT (possibly wrong):\n";
echo "  assetTotal:          {$assetTotal}\n";
echo "  liabTotal:           {$liabTotal}\n";
echo "  equityAccountsTotal: {$equityAccountsTotal}\n";
echo "  retainedEarnings:    {$retainedEarnings}\n";
echo "  equityTotal:         {$equityTotal}\n";
echo "  liab+equity:         {$liabEquityTotal}\n";
echo "  balanced:            " . (abs($assetTotal - $liabEquityTotal) < 0.01 ? 'YES' : 'NO (diff=' . ($assetTotal - $liabEquityTotal) . ')') . "\n";

// Fixed calculation
$assetOnly   = $assets->where('type', 'Asset');
$contraOnly  = $assets->where('type', 'Contra Asset');
$assetTotalFixed = $assetOnly->sum(fn($c) => calcBalance($c, $asOf))
                 - $contraOnly->sum(fn($c) => calcBalance($c, $asOf));

$openingAssetsFixed    = ChartOfAccount::where('type', 'Asset')->sum('opening_balance')
                       - ChartOfAccount::where('type', 'Contra Asset')->sum('opening_balance');
$openingImbalanceFixed = $openingAssetsFixed - $openingLiab - $openingEquity;
$retainedFixed         = ($rev - $exp) - $openingImbalanceFixed;

$equityTotalFixed    = $equityAccountsTotal + $retainedFixed;
$liabEquityFixed     = $liabTotal + $equityTotalFixed;

echo "\nFIXED (contra asset subtracted):\n";
echo "  assetTotalFixed:     {$assetTotalFixed}\n";
echo "  liabTotal:           {$liabTotal}\n";
echo "  equityAccountsTotal: {$equityAccountsTotal}\n";
echo "  retainedFixed:       {$retainedFixed}\n";
echo "  equityTotalFixed:    {$equityTotalFixed}\n";
echo "  liab+equityFixed:    {$liabEquityFixed}\n";
echo "  balanced:            " . (abs($assetTotalFixed - $liabEquityFixed) < 0.01 ? 'YES' : 'NO (diff=' . ($assetTotalFixed - $liabEquityFixed) . ')') . "\n";
