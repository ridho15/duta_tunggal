<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var \App\Services\BalanceSheetService $service */
$service = app(\App\Services\BalanceSheetService::class);
// Use default filters (as_of_date = today)
$result = $service->generate([]);

$output = [
    'as_of_date' => $result['as_of_date'] ?? null,
    'total_assets' => $result['total_assets'] ?? null,
    'total_liabilities_and_equity' => $result['total_liabilities_and_equity'] ?? null,
    'is_balanced' => $result['is_balanced'] ?? null,
    'difference' => $result['difference'] ?? null,
    'retained_earnings' => $result['retained_earnings'] ?? null,
];

$output['total_liabilities'] = $result['total_liabilities'] ?? null;
$output['total_equity_before_retained'] = $result['equity']['total'] ?? null;
$output['total_equity_with_retained'] = $result['total_equity'] ?? null;

// Additionally compute raw revenue and expense totals used to build retained earnings
$asOfDate = $result['as_of_date'] ?? date('Y-m-d');
$revenueAccountIds = \App\Models\ChartOfAccount::where('type', 'Revenue')->where('is_active', true)->whereNull('deleted_at')->pluck('id');
$expenseAccountIds = \App\Models\ChartOfAccount::where('type', 'Expense')->where('is_active', true)->whereNull('deleted_at')->pluck('id');

$revenueQuery = \App\Models\JournalEntry::whereIn('coa_id', $revenueAccountIds)->whereDate('date', '<=', $asOfDate);
$expenseQuery = \App\Models\JournalEntry::whereIn('coa_id', $expenseAccountIds)->whereDate('date', '<=', $asOfDate);

$totalRevenueDebit = (float) $revenueQuery->sum('debit');
$totalRevenueCredit = (float) $revenueQuery->sum('credit');
$totalRevenue = $totalRevenueCredit - $totalRevenueDebit;

$totalExpenseDebit = (float) $expenseQuery->sum('debit');
$totalExpenseCredit = (float) $expenseQuery->sum('credit');
$totalExpense = $totalExpenseDebit - $totalExpenseCredit;

$output['raw_revenue_debit'] = $totalRevenueDebit;
$output['raw_revenue_credit'] = $totalRevenueCredit;
$output['raw_total_revenue'] = $totalRevenue;
$output['raw_expense_debit'] = $totalExpenseDebit;
$output['raw_expense_credit'] = $totalExpenseCredit;
$output['raw_total_expense'] = $totalExpense;

echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
