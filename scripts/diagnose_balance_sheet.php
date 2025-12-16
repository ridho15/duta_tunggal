<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BalanceSheetService;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;

$asOfDate = date('Y-m-d');
$service = app(BalanceSheetService::class);
$result = $service->generate(['as_of_date' => $asOfDate]);

// Collect liabilities (current + long term)
$liabilities = collect();
if (!empty($result['current_liabilities']['accounts'])) {
    $liabilities = $liabilities->merge($result['current_liabilities']['accounts']);
}
if (!empty($result['long_term_liabilities']['accounts'])) {
    $liabilities = $liabilities->merge($result['long_term_liabilities']['accounts']);
}

// Convert to simple array of objects with balance
$liabilities = $liabilities->map(function($a){
    return (object) [
        'id' => $a->id,
        'code' => $a->code ?? $a->kode ?? null,
        'name' => $a->name ?? $a->nama ?? null,
        'balance' => (float) ($a->balance ?? 0),
        'total_debit' => (float) ($a->total_debit ?? 0),
        'total_credit' => (float) ($a->total_credit ?? 0),
        'entries_count' => (int) ($a->entries_count ?? 0),
    ];
});

$topLiabilities = $liabilities->sortByDesc('balance')->values()->slice(0, 10);

// Equity accounts
$equityAccounts = collect();
if (!empty($result['equity']['accounts'])) {
    $equityAccounts = collect($result['equity']['accounts'])->map(function($a){
        return (object) [
            'id' => $a->id,
            'code' => $a->code ?? $a->kode ?? null,
            'name' => $a->name ?? $a->nama ?? null,
            'opening_balance' => (float) ($a->opening_balance ?? 0),
            'balance' => (float) ($a->balance ?? 0),
            'total_debit' => (float) ($a->total_debit ?? 0),
            'total_credit' => (float) ($a->total_credit ?? 0),
            'entries_count' => (int) ($a->entries_count ?? 0),
        ];
    });
}

// Try to locate a retained earnings account by name
$retainedCandidates = ChartOfAccount::where('type', 'Equity')
    ->where(function($q){
        $q->where('name', 'like', '%Retain%')
          ->orWhere('name', 'like', '%Laba Ditahan%')
          ->orWhere('name', 'like', '%Laba%')
          ->orWhere('name', 'like', '%Retained%');
    })
    ->get();

$retainedAccount = $retainedCandidates->first();

$recentEquityEntries = [];
if ($retainedAccount) {
    $entries = JournalEntry::where('coa_id', $retainedAccount->id)
        ->orderBy('date', 'desc')
        ->orderBy('id', 'desc')
        ->limit(20)
        ->get();
    $recentEquityEntries = $entries->map(function($e){
        return [
            'id' => $e->id,
            'date' => $e->date->format('Y-m-d'),
            'coa_id' => $e->coa_id,
            'debit' => (float)$e->debit,
            'credit' => (float)$e->credit,
            'journal_type' => $e->journal_type,
            'reference' => $e->reference,
            'description' => $e->description,
            'source_type' => $e->source_type,
            'source_id' => $e->source_id,
        ];
    });
} else {
    // If no retained account found, show recent entries touching any equity account
    $equityIds = ChartOfAccount::where('type', 'Equity')->pluck('id');
    $entries = JournalEntry::whereIn('coa_id', $equityIds)
        ->orderBy('date', 'desc')
        ->orderBy('id', 'desc')
        ->limit(20)
        ->get();
    $recentEquityEntries = $entries->map(function($e){
        return [
            'id' => $e->id,
            'date' => $e->date->format('Y-m-d'),
            'coa_id' => $e->coa_id,
            'debit' => (float)$e->debit,
            'credit' => (float)$e->credit,
            'journal_type' => $e->journal_type,
            'reference' => $e->reference,
            'description' => $e->description,
            'source_type' => $e->source_type,
            'source_id' => $e->source_id,
        ];
    });
}

$output = [
    'as_of_date' => $asOfDate,
    'total_assets' => $result['total_assets'] ?? null,
    'total_liabilities' => $result['total_liabilities'] ?? null,
    'total_equity_before_retained' => $result['equity']['total'] ?? null,
    'retained_earnings' => $result['retained_earnings'] ?? null,
    'total_liabilities_and_equity' => $result['total_liabilities_and_equity'] ?? null,
    'is_balanced' => $result['is_balanced'] ?? null,
    'difference' => $result['difference'] ?? null,
    'top_liabilities' => $topLiabilities->values(),
    'equity_accounts' => $equityAccounts->values(),
    'retained_account_found' => $retainedAccount ? true : false,
    'retained_account' => $retainedAccount ? [
        'id' => $retainedAccount->id,
        'code' => $retainedAccount->code ?? null,
        'name' => $retainedAccount->name ?? null,
    ] : null,
    'recent_equity_entries' => $recentEquityEntries->values(),
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
