<?php

declare(strict_types=1);

use App\Models\Asset;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\CashBankTransaction;
use App\Models\CustomerReceiptItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Reports\CashFlowCashAccount;
use App\Models\Reports\CashFlowSection;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\ProfitLossMultiDivisionService;
use App\Services\Reports\CashFlowReportService;
use App\Services\Reports\DrillDownFinancialReportService;
use App\Services\Reports\InventoryCardReportService;
use App\Services\Reports\JournalConsolidationReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$journalStart = '2026-01-01';
$journalEnd = '2026-04-08';
$cashStart = '2026-04-01';
$cashEnd = '2026-04-30';

$journalService = app(JournalConsolidationReportService::class)->generate([
    'start_date' => $journalStart,
    'end_date' => $journalEnd,
    'group_by_branch' => true,
]);

$journalEntries = JournalEntry::with(['coa', 'cabang'])
    ->whereBetween('date', [$journalStart . ' 00:00:00', $journalEnd . ' 23:59:59'])
    ->orderBy('date')
    ->orderBy('transaction_id')
    ->orderBy('id')
    ->get();

$directJournalGroups = $journalEntries
    ->groupBy(fn (JournalEntry $entry) => $entry->cabang_id ?: 'uncategorized')
    ->map(function ($lines, $branchKey) {
        $first = $lines->first();
        $branch = $first?->cabang;

        return [
            'cabang_id' => is_numeric($branchKey) ? (int) $branchKey : null,
            'cabang_name' => $branch?->nama ?: 'Tanpa Cabang',
            'count' => $lines->count(),
            'total_debit' => (float) $lines->sum('debit'),
            'total_credit' => (float) $lines->sum('credit'),
            'balance' => (float) $lines->sum('debit') - (float) $lines->sum('credit'),
        ];
    })
    ->sortBy('cabang_name', SORT_NATURAL | SORT_FLAG_CASE)
    ->values()
    ->all();

$serviceJournalGroups = collect($journalService['grouped'])
    ->map(fn (array $group) => [
        'cabang_id' => $group['cabang_id'],
        'cabang_name' => $group['cabang_name'],
        'count' => $group['count'],
        'total_debit' => (float) $group['total_debit'],
        'total_credit' => (float) $group['total_credit'],
        'balance' => (float) $group['balance'],
    ])
    ->values()
    ->all();

$directCoaSummary = $journalEntries
    ->groupBy('coa_id')
    ->map(function ($lines) {
        $coa = $lines->first()?->coa;

        return [
            'code' => $coa?->code,
            'total_debit' => (float) $lines->sum('debit'),
            'total_credit' => (float) $lines->sum('credit'),
            'balance' => (float) $lines->sum('debit') - (float) $lines->sum('credit'),
        ];
    })
    ->sortBy('code', SORT_NATURAL | SORT_FLAG_CASE)
    ->values()
    ->all();

$serviceCoaSummary = collect($journalService['coa_summary'])
    ->map(fn (array $row) => [
        'code' => $row['coa']?->code,
        'total_debit' => (float) $row['total_debit'],
        'total_credit' => (float) $row['total_credit'],
        'balance' => (float) $row['balance'],
    ])
    ->values()
    ->all();

$drillService = app(DrillDownFinancialReportService::class)->generate([
    'start_date' => $journalStart,
    'end_date' => $journalEnd,
]);

$directDrillGroups = $journalEntries
    ->groupBy('coa_id')
    ->map(function ($lines) {
        $coa = $lines->first()?->coa;
        $debit = (float) $lines->sum('debit');
        $credit = (float) $lines->sum('credit');
        $debitNormal = in_array($coa?->type, ['Asset', 'Expense'], true);

        return [
            'code' => $coa?->code,
            'line_count' => $lines->count(),
            'total_debit' => $debit,
            'total_credit' => $credit,
            'balance' => $debitNormal ? $debit - $credit : $credit - $debit,
        ];
    })
    ->sortBy('code', SORT_NATURAL | SORT_FLAG_CASE)
    ->values()
    ->all();

$serviceDrillGroups = collect($drillService['grouped'])
    ->map(fn (array $row) => [
        'code' => $row['coa']?->code,
        'line_count' => count($row['lines'] ?? []),
        'total_debit' => (float) $row['total_debit'],
        'total_credit' => (float) $row['total_credit'],
        'balance' => (float) $row['balance'],
    ])
    ->values()
    ->all();

$cashService = app(CashFlowReportService::class)->generate($cashStart, $cashEnd, ['method' => 'direct']);

$receiptQuery = CustomerReceiptItem::query()
    ->where(function (Builder $query) use ($cashStart, $cashEnd) {
        $query->whereBetween('payment_date', [$cashStart, $cashEnd])
            ->orWhere(function (Builder $nested) use ($cashStart, $cashEnd) {
                $nested->whereNull('payment_date')
                    ->whereHas('customerReceipt', function (Builder $receiptQuery) use ($cashStart, $cashEnd) {
                        $receiptQuery->whereBetween('payment_date', [$cashStart, $cashEnd]);
                    });
            });
    })
    ->whereHas('customerReceipt', function (Builder $query) {
        $query->whereIn('payment_method', ['Cash', 'Bank', 'Bank Transfer'])
            ->whereIn(DB::raw('LOWER(status)'), ['paid', 'partial']);
    });

$cashPrefixes = CashFlowCashAccount::query()->pluck('prefix')->filter()->unique()->values()->all();

$applyPrefix = static function (Builder $query, string $column, array $prefixes): Builder {
    $query->where(function (Builder $inner) use ($column, $prefixes) {
        foreach ($prefixes as $prefix) {
            $inner->orWhere($column, 'like', $prefix . '%');
        }
    });

    return $query;
};

$sumCashBank = static function (array $prefixes, array $types) use ($cashStart, $cashEnd, $applyPrefix): float {
    if ($prefixes === []) {
        return 0.0;
    }

    return (float) CashBankTransaction::query()
        ->whereBetween('date', [$cashStart, $cashEnd])
        ->whereIn('type', $types)
        ->whereHas('offsetCoa', function (Builder $coaQuery) use ($prefixes, $applyPrefix) {
            $applyPrefix($coaQuery, 'code', $prefixes);
        })
        ->sum('amount');
};

$sumAssets = static function (array $prefixes, string $type) use ($cashStart, $cashEnd, $applyPrefix): float {
    if ($prefixes === []) {
        return 0.0;
    }

    $sum = (float) Asset::query()
        ->whereBetween('purchase_date', [$cashStart, $cashEnd])
        ->whereHas('assetCoa', function (Builder $coaQuery) use ($prefixes, $applyPrefix) {
            $applyPrefix($coaQuery, 'code', $prefixes);
        })
        ->sum('purchase_cost');

    return match ($type) {
        'inflow' => $sum,
        'net' => -1 * $sum,
        default => -1 * $sum,
    };
};

$sumSalesReceipts = static function () use ($cashStart, $cashEnd): float {
    return (float) CustomerReceiptItem::query()
        ->where(function (Builder $query) use ($cashStart, $cashEnd) {
            $query->whereBetween('payment_date', [$cashStart, $cashEnd])
                ->orWhere(function (Builder $nested) use ($cashStart, $cashEnd) {
                    $nested->whereNull('payment_date')
                        ->whereHas('customerReceipt', function (Builder $receiptQuery) use ($cashStart, $cashEnd) {
                            $receiptQuery->whereBetween('payment_date', [$cashStart, $cashEnd]);
                        });
                });
        })
        ->whereHas('customerReceipt', function (Builder $query) {
            $query->whereIn('payment_method', ['Cash', 'Bank', 'Bank Transfer'])
                ->whereIn(DB::raw('LOWER(status)'), ['paid', 'partial']);
        })
        ->sum('amount');
};

$sumCustomerDeposits = static function () use ($cashStart, $cashEnd, $cashPrefixes, $applyPrefix): float {
    if ($cashPrefixes === []) {
        return 0.0;
    }

    $query = JournalEntry::query()
        ->join('chart_of_accounts', 'journal_entries.coa_id', '=', 'chart_of_accounts.id')
        ->join('deposits', function ($join) {
            $join->on('deposits.id', '=', 'journal_entries.source_id')
                ->where('journal_entries.source_type', App\Models\Deposit::class);
        })
        ->whereBetween('journal_entries.date', [$cashStart, $cashEnd])
        ->where('journal_entries.source_type', App\Models\Deposit::class)
        ->where('journal_entries.journal_type', 'deposit')
        ->where('deposits.from_model_type', App\Models\Customer::class)
        ->where(function (Builder $query) use ($cashPrefixes, $applyPrefix) {
            $applyPrefix($query, 'chart_of_accounts.code', $cashPrefixes);
        });

    return (float) $query->sum('journal_entries.debit') - (float) $query->sum('journal_entries.credit');
};

$sumSupplierDeposits = static function () use ($cashStart, $cashEnd, $cashPrefixes, $applyPrefix): float {
    if ($cashPrefixes === []) {
        return 0.0;
    }

    $query = JournalEntry::query()
        ->join('chart_of_accounts', 'journal_entries.coa_id', '=', 'chart_of_accounts.id')
        ->join('deposits', function ($join) {
            $join->on('deposits.id', '=', 'journal_entries.source_id')
                ->where('journal_entries.source_type', App\Models\Deposit::class);
        })
        ->whereBetween('journal_entries.date', [$cashStart, $cashEnd])
        ->where('journal_entries.source_type', App\Models\Deposit::class)
        ->where('journal_entries.journal_type', 'deposit')
        ->where('deposits.from_model_type', App\Models\Supplier::class)
        ->where(function (Builder $query) use ($cashPrefixes, $applyPrefix) {
            $applyPrefix($query, 'chart_of_accounts.code', $cashPrefixes);
        });

    $amount = (float) $query->sum('journal_entries.credit') - (float) $query->sum('journal_entries.debit');

    return -1 * abs($amount);
};

$sumJournal = static function (array $prefixes, string $type) use ($cashStart, $cashEnd, $applyPrefix): float {
    if ($prefixes === []) {
        return 0.0;
    }

    $entries = JournalEntry::query()
        ->join('chart_of_accounts', 'journal_entries.coa_id', '=', 'chart_of_accounts.id')
        ->whereBetween('journal_entries.date', [$cashStart, $cashEnd])
        ->where(function (Builder $query) {
            $query->where(function (Builder $entryQuery) {
                $entryQuery->where('journal_entries.source_type', App\Models\CashBankTransfer::class)
                    ->where('journal_entries.journal_type', 'transfer');
            })->orWhere(function (Builder $entryQuery) {
                $entryQuery->where('journal_entries.source_type', App\Models\VendorPayment::class)
                    ->where('journal_entries.journal_type', 'payment');
            });
        })
        ->where(function (Builder $query) use ($prefixes, $applyPrefix) {
            $applyPrefix($query, 'chart_of_accounts.code', $prefixes);
        })
        ->get([
            'journal_entries.debit',
            'journal_entries.credit',
            'journal_entries.source_type',
            'journal_entries.journal_type',
            'chart_of_accounts.type as coa_type',
        ]);

    $inflow = 0.0;
    $outflow = 0.0;
    $net = 0.0;

    foreach ($entries as $entry) {
        $debit = (float) $entry->debit;
        $credit = (float) $entry->credit;
        $accountType = strtolower((string) $entry->coa_type);

        if ($accountType === 'revenue') {
            $amount = $credit - $debit;
        } elseif ($accountType === 'liability' && $entry->source_type === App\Models\VendorPayment::class && $entry->journal_type === 'payment') {
            $amount = $credit - $debit;
        } else {
            $amount = $debit - $credit;
        }

        $net += $amount;

        if ($amount > 0) {
            $inflow += $amount;
        } elseif ($amount < 0) {
            $outflow += $amount;
        }
    }

    return match ($type) {
        'inflow' => $inflow,
        'outflow' => $outflow,
        'net' => $net,
        default => 0.0,
    };
};

$openingBase = CashBankTransaction::query()
    ->where('date', '<', $cashStart)
    ->where(function (Builder $query) use ($cashPrefixes, $applyPrefix) {
        $query->whereHas('accountCoa', function (Builder $accountQuery) use ($cashPrefixes, $applyPrefix) {
            $applyPrefix($accountQuery, 'code', $cashPrefixes);
        })->orWhereHas('offsetCoa', function (Builder $offsetQuery) use ($cashPrefixes, $applyPrefix) {
            $applyPrefix($offsetQuery, 'code', $cashPrefixes);
        });
    })
    ->where(function (Builder $query) use ($cashPrefixes, $applyPrefix) {
        $query->whereDoesntHave('accountCoa', function (Builder $accountQuery) use ($cashPrefixes, $applyPrefix) {
            $applyPrefix($accountQuery, 'code', $cashPrefixes);
        })->orWhereDoesntHave('offsetCoa', function (Builder $offsetQuery) use ($cashPrefixes, $applyPrefix) {
            $applyPrefix($offsetQuery, 'code', $cashPrefixes);
        });
    });

$openingInflows = (float) (clone $openingBase)->whereIn('type', ['cash_in', 'bank_in'])->sum('amount');
$openingOutflows = (float) (clone $openingBase)->whereIn('type', ['cash_out', 'bank_out'])->sum('amount');

$openingJournalBase = JournalEntry::query()
    ->where('date', '<', $cashStart)
    ->where('source_type', App\Models\CashBankTransfer::class)
    ->where('journal_type', 'transfer')
    ->whereHas('coa', function (Builder $query) use ($cashPrefixes, $applyPrefix) {
        $applyPrefix($query, 'code', $cashPrefixes);
    });

$openingJournal = (float) (clone $openingJournalBase)->sum('debit') - (float) (clone $openingJournalBase)->sum('credit');
$openingPrefixed = Schema::hasColumn('report_cash_flow_cash_accounts', 'opening_balance')
    ? (float) CashFlowCashAccount::query()->sum('opening_balance')
    : 0.0;
$openingDirect = round(($openingInflows - $openingOutflows) + $openingJournal + $openingPrefixed, 2);

$directCashSections = CashFlowSection::query()
    ->with(['items' => function ($query) {
        $query->with(['prefixes', 'sources'])->orderBy('sort_order');
    }])
    ->orderBy('sort_order')
    ->get()
    ->map(function (CashFlowSection $section) use ($sumSalesReceipts, $sumCustomerDeposits, $sumSupplierDeposits, $sumCashBank, $sumJournal, $sumAssets) {
        $items = [];
        $total = 0.0;

        foreach ($section->items as $item) {
            $amount = match ($item->resolver) {
                'salesReceipts' => $sumSalesReceipts(),
                'customerDeposits' => $sumCustomerDeposits(),
                'supplierDeposits' => $sumSupplierDeposits(),
                default => (function () use ($item, $sumCashBank, $sumJournal) {
                    $prefixes = $item->prefixes->where('is_asset', false)->pluck('prefix')->toArray();
                    if ($prefixes === []) {
                        return 0.0;
                    }

                    $cashBankAmount = match ($item->type) {
                        'inflow' => $sumCashBank($prefixes, ['cash_in', 'bank_in']),
                        'outflow' => -1 * $sumCashBank($prefixes, ['cash_out', 'bank_out']),
                        'net' => $sumCashBank($prefixes, ['cash_in', 'bank_in']) - $sumCashBank($prefixes, ['cash_out', 'bank_out']),
                        default => 0.0,
                    };

                    return $cashBankAmount + $sumJournal($prefixes, $item->type);
                })(),
            };

            if ($item->include_assets) {
                $assetPrefixes = $item->prefixes->where('is_asset', true)->pluck('prefix')->toArray();
                if ($assetPrefixes !== []) {
                    $amount += $sumAssets($assetPrefixes, $item->type);
                }
            }

            $amount = round($amount, 2);
            $items[] = [
                'key' => $item->key,
                'amount' => $amount,
            ];
            $total += $amount;
        }

        return [
            'key' => $section->key,
            'items' => $items,
            'total' => round($total, 2),
        ];
    })
    ->values()
    ->all();

$serviceCashSections = collect($cashService['sections'] ?? [])
    ->map(fn (array $section) => [
        'key' => $section['key'],
        'items' => collect($section['items'] ?? [])->map(fn (array $item) => [
            'key' => $item['key'],
            'amount' => (float) $item['amount'],
        ])->values()->all(),
        'total' => (float) $section['total'],
    ])
    ->values()
    ->all();

$cashNetDirect = round(collect($directCashSections)->sum('total'), 2);
$cashClosingDirect = round($openingDirect + $cashNetDirect, 2);

$profitStart = '2026-01-01';
$profitEnd = '2026-04-08';
$profitServiceObject = app(ProfitLossMultiDivisionService::class);
$profitService = $profitServiceObject->generate($profitStart, $profitEnd);

$profitDivisions = Cabang::query()->orderBy('kode')->get();
$profitDivIds = $profitDivisions->pluck('id')->map(fn ($id) => (int) $id)->toArray();

$profitAccounts = ChartOfAccount::query()
    ->whereIn('type', ['Revenue', 'Expense'])
    ->where('is_active', true)
    ->orderBy('code')
    ->get()
    ->keyBy('id');

$profitBalanceRows = JournalEntry::withoutGlobalScopes()
    ->selectRaw('coa_id, cabang_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
    ->whereIn('coa_id', $profitAccounts->keys()->all())
    ->whereBetween('date', [$profitStart . ' 00:00:00', $profitEnd . ' 23:59:59'])
    ->whereIn('cabang_id', $profitDivIds)
    ->groupBy('coa_id', 'cabang_id')
    ->get();

$profitBalanceMap = [];
foreach ($profitBalanceRows as $row) {
    $profitBalanceMap[(int) $row->coa_id][(int) $row->cabang_id] = [
        'debit' => (float) $row->total_debit,
        'credit' => (float) $row->total_credit,
    ];
}

$profitChildrenOf = [];
foreach ($profitAccounts as $account) {
    if ($account->parent_id !== null) {
        $profitChildrenOf[(int) $account->parent_id][] = (int) $account->id;
    }
}

$profitLeafSet = $profitAccounts->keys()
    ->filter(fn (int $id) => empty($profitChildrenOf[$id] ?? []))
    ->flip()
    ->toArray();

$profitGetBalance = static function (int $coaId, int $divId, string $type) use (&$profitBalanceMap): float {
    $entry = $profitBalanceMap[$coaId][$divId] ?? ['debit' => 0.0, 'credit' => 0.0];

    return $type === 'Revenue'
        ? ((float) $entry['credit'] - (float) $entry['debit'])
        : ((float) $entry['debit'] - (float) $entry['credit']);
};

$profitAccountTotal = null;
$profitAccountTotal = static function (int $accountId, int $divId, string $type) use (&$profitAccountTotal, $profitChildrenOf, $profitLeafSet, $profitGetBalance): float {
    if (isset($profitLeafSet[$accountId])) {
        return $profitGetBalance($accountId, $divId, $type);
    }

    $total = 0.0;
    foreach (($profitChildrenOf[$accountId] ?? []) as $childId) {
        $total += $profitAccountTotal($childId, $divId, $type);
    }

    return $total;
};

$profitVectorsMatch = static function (array $left, array $right, array $divIds): bool {
    foreach ($divIds as $divId) {
        if (abs(((float) ($left[$divId] ?? 0.0)) - ((float) ($right[$divId] ?? 0.0))) >= 0.001) {
            return false;
        }
    }

    return true;
};

$profitRevenueAccounts = $profitAccounts->filter(fn ($account) => $account->type === 'Revenue');
$profitRevenueIds = $profitRevenueAccounts->pluck('id')->flip()->toArray();
$profitRevenueTopLevel = $profitRevenueAccounts->filter(
    fn ($account) => $account->parent_id === null || !isset($profitRevenueIds[$account->parent_id])
);

$profitCogsAccounts = $profitAccounts->filter(fn ($account) => $account->type === 'Expense' && str_starts_with($account->code, '5'));
$profitCogsIds = $profitCogsAccounts->pluck('id')->flip()->toArray();
$profitCogsTopLevel = $profitCogsAccounts->filter(
    fn ($account) => $account->parent_id === null || !isset($profitCogsIds[$account->parent_id])
);

$profitOpexAccounts = $profitAccounts->filter(fn ($account) => $account->type === 'Expense' && str_starts_with($account->code, '6'));
$profitOpexIds = $profitOpexAccounts->pluck('id')->flip()->toArray();
$profitOpexTopLevel = $profitOpexAccounts->filter(
    fn ($account) => $account->parent_id === null || !isset($profitOpexIds[$account->parent_id])
);

$profitTotalRevenue = array_fill_keys($profitDivIds, 0.0);
foreach ($profitRevenueTopLevel as $account) {
    foreach ($profitDivIds as $divId) {
        $profitTotalRevenue[$divId] += $profitAccountTotal((int) $account->id, $divId, 'Revenue');
    }
}

$profitTotalCogs = array_fill_keys($profitDivIds, 0.0);
foreach ($profitCogsTopLevel as $account) {
    foreach ($profitDivIds as $divId) {
        $profitTotalCogs[$divId] += $profitAccountTotal((int) $account->id, $divId, 'Expense');
    }
}

$profitTotalOpex = array_fill_keys($profitDivIds, 0.0);
foreach ($profitOpexTopLevel as $account) {
    foreach ($profitDivIds as $divId) {
        $profitTotalOpex[$divId] += $profitAccountTotal((int) $account->id, $divId, 'Expense');
    }
}

$profitTotalOther = array_fill_keys($profitDivIds, 0.0);
foreach ($profitAccounts->filter(fn ($account) => $account->type === 'Revenue' && !str_starts_with($account->code, '4') && isset($profitLeafSet[$account->id])) as $account) {
    foreach ($profitDivIds as $divId) {
        $profitTotalOther[$divId] += $profitGetBalance((int) $account->id, $divId, 'Revenue');
    }
}

foreach ($profitAccounts->filter(fn ($account) => $account->type === 'Expense' && !str_starts_with($account->code, '5') && !str_starts_with($account->code, '6') && isset($profitLeafSet[$account->id])) as $account) {
    foreach ($profitDivIds as $divId) {
        $profitTotalOther[$divId] -= $profitGetBalance((int) $account->id, $divId, 'Expense');
    }
}

$profitGross = $profitServiceObject->subtractVectors($profitTotalRevenue, $profitTotalCogs, $profitDivIds);
$profitOperating = $profitServiceObject->subtractVectors($profitGross, $profitTotalOpex, $profitDivIds);
$profitNet = $profitServiceObject->addVectors($profitOperating, $profitTotalOther, $profitDivIds);

$profitLossDirect = [
    'division_count' => count($profitDivIds),
    'total_revenue_match' => $profitVectorsMatch($profitService['total_revenue'], $profitTotalRevenue, $profitDivIds),
    'total_cogs_match' => $profitVectorsMatch($profitService['total_cogs'], $profitTotalCogs, $profitDivIds),
    'gross_profit_match' => $profitVectorsMatch($profitService['gross_profit'], $profitGross, $profitDivIds),
    'total_opex_match' => $profitVectorsMatch($profitService['total_opex'], $profitTotalOpex, $profitDivIds),
    'operating_profit_match' => $profitVectorsMatch($profitService['operating_profit'], $profitOperating, $profitDivIds),
    'total_other_match' => $profitVectorsMatch($profitService['total_other'], $profitTotalOther, $profitDivIds),
    'net_profit_match' => $profitVectorsMatch($profitService['net_profit'], $profitNet, $profitDivIds),
    'all_match' =>
        $profitVectorsMatch($profitService['total_revenue'], $profitTotalRevenue, $profitDivIds)
        && $profitVectorsMatch($profitService['total_cogs'], $profitTotalCogs, $profitDivIds)
        && $profitVectorsMatch($profitService['gross_profit'], $profitGross, $profitDivIds)
        && $profitVectorsMatch($profitService['total_opex'], $profitTotalOpex, $profitDivIds)
        && $profitVectorsMatch($profitService['operating_profit'], $profitOperating, $profitDivIds)
        && $profitVectorsMatch($profitService['total_other'], $profitTotalOther, $profitDivIds)
        && $profitVectorsMatch($profitService['net_profit'], $profitNet, $profitDivIds),
    'net_mismatches' => collect($profitDivIds)
        ->filter(fn (int $divId) => abs(((float) ($profitService['net_profit'][$divId] ?? 0.0)) - ((float) ($profitNet[$divId] ?? 0.0))) >= 0.001)
        ->values()
        ->all(),
];

$inventoryStart = '2026-04-01';
$inventoryEnd = '2026-04-30';
$inventoryService = app(InventoryCardReportService::class)->reportData([
    'start' => $inventoryStart,
    'end' => $inventoryEnd,
]);

$inventoryQuery = static function () {
    $inTypes = "'purchase_in','manufacture_in','transfer_in','adjustment_in'";
    $outTypes = "'sales','transfer_out','manufacture_out','adjustment_out'";

    return StockMovement::query()->selectRaw(
        'product_id, warehouse_id, '
        . "SUM(CASE WHEN type IN ($inTypes) THEN quantity ELSE 0 END) AS qty_in, "
        . "SUM(CASE WHEN type IN ($outTypes) THEN quantity ELSE 0 END) AS qty_out, "
        . "SUM(CASE WHEN type IN ($inTypes) THEN COALESCE(value,0) ELSE 0 END) AS value_in, "
        . "SUM(CASE WHEN type IN ($outTypes) THEN COALESCE(value,0) ELSE 0 END) AS value_out"
    )->groupBy('product_id', 'warehouse_id');
};

$inventoryOpening = $inventoryQuery()
    ->where('date', '<', $inventoryStart . ' 00:00:00')
    ->get()
    ->keyBy(fn ($row) => $row->product_id . '-' . $row->warehouse_id);

$inventoryPeriod = $inventoryQuery()
    ->whereBetween('date', [$inventoryStart . ' 00:00:00', $inventoryEnd . ' 23:59:59'])
    ->get()
    ->keyBy(fn ($row) => $row->product_id . '-' . $row->warehouse_id);

$inventoryKeys = $inventoryOpening->keys()->merge($inventoryPeriod->keys())->unique()->values();
$inventoryProducts = Product::whereIn('id', $inventoryKeys->map(fn ($key) => (int) explode('-', $key)[0])->unique())->get()->keyBy('id');
$inventoryWarehouses = Warehouse::whereIn('id', $inventoryKeys->map(fn ($key) => (int) explode('-', $key)[1])->unique())->get()->keyBy('id');

$inventoryRows = [];
$inventoryTotals = [
    'opening_qty' => 0.0,
    'opening_value' => 0.0,
    'qty_in' => 0.0,
    'value_in' => 0.0,
    'qty_out' => 0.0,
    'value_out' => 0.0,
    'closing_qty' => 0.0,
    'closing_value' => 0.0,
];

foreach ($inventoryKeys as $key) {
    [$productKey, $warehouseKey] = array_map('intval', explode('-', $key));
    $opening = $inventoryOpening[$key] ?? null;
    $movement = $inventoryPeriod[$key] ?? null;

    $openingQty = (float) (($opening->qty_in ?? 0) - ($opening->qty_out ?? 0));
    $openingValue = (float) (($opening->value_in ?? 0) - ($opening->value_out ?? 0));
    $qtyIn = (float) ($movement->qty_in ?? 0);
    $valueIn = (float) ($movement->value_in ?? 0);
    $qtyOut = (float) ($movement->qty_out ?? 0);
    $valueOut = (float) ($movement->value_out ?? 0);

    if ($qtyIn == 0.0 && $qtyOut == 0.0 && $valueIn == 0.0 && $valueOut == 0.0) {
        continue;
    }

    $closingQty = $openingQty + $qtyIn - $qtyOut;
    $closingValue = $openingValue + $valueIn - $valueOut;

    $inventoryRows[] = [
        'product_name' => $inventoryProducts->get($productKey)?->name ?? '-',
        'product_sku' => $inventoryProducts->get($productKey)?->sku ?? null,
        'warehouse_name' => $inventoryWarehouses->get($warehouseKey)?->name ?? '-',
        'warehouse_code' => $inventoryWarehouses->get($warehouseKey)?->kode ?? null,
        'opening_qty' => $openingQty,
        'opening_value' => $openingValue,
        'qty_in' => $qtyIn,
        'value_in' => $valueIn,
        'qty_out' => $qtyOut,
        'value_out' => $valueOut,
        'closing_qty' => $closingQty,
        'closing_value' => $closingValue,
    ];

    $inventoryTotals['opening_qty'] += $openingQty;
    $inventoryTotals['opening_value'] += $openingValue;
    $inventoryTotals['qty_in'] += $qtyIn;
    $inventoryTotals['value_in'] += $valueIn;
    $inventoryTotals['qty_out'] += $qtyOut;
    $inventoryTotals['value_out'] += $valueOut;
    $inventoryTotals['closing_qty'] += $closingQty;
    $inventoryTotals['closing_value'] += $closingValue;
}

$inventoryServiceRows = array_map(static function (array $row): array {
    return [
        'product_name' => $row['product_name'],
        'product_sku' => $row['product_sku'],
        'warehouse_name' => $row['warehouse_name'],
        'warehouse_code' => $row['warehouse_code'],
        'opening_qty' => (float) $row['opening_qty'],
        'opening_value' => (float) $row['opening_value'],
        'qty_in' => (float) $row['qty_in'],
        'value_in' => (float) $row['value_in'],
        'qty_out' => (float) $row['qty_out'],
        'value_out' => (float) $row['value_out'],
        'closing_qty' => (float) $row['closing_qty'],
        'closing_value' => (float) $row['closing_value'],
    ];
}, $inventoryService['rows']);

$inventoryServiceTotals = array_map(static fn ($value) => (float) $value, $inventoryService['totals']);

$inventoryDirect = [
    'period_match' => $inventoryService['period'] === ['start' => $inventoryStart, 'end' => $inventoryEnd],
    'label_match' => $inventoryService['product_label'] === 'Semua Produk' && $inventoryService['warehouse_label'] === 'Semua Gudang',
    'rows_match' => $inventoryServiceRows === $inventoryRows,
    'totals_match' => $inventoryServiceTotals === $inventoryTotals,
    'row_count' => count($inventoryRows),
    'total_opening_qty' => $inventoryTotals['opening_qty'],
    'total_closing_value' => $inventoryTotals['closing_value'],
];

$results = [
    'journal_consolidation' => [
        'count_match' => $journalService['count'] === $journalEntries->count(),
        'total_debit_match' => abs((float) $journalService['total_debit'] - (float) $journalEntries->sum('debit')) < 0.001,
        'total_credit_match' => abs((float) $journalService['total_credit'] - (float) $journalEntries->sum('credit')) < 0.001,
        'difference_match' => abs((float) $journalService['difference'] - ((float) $journalEntries->sum('debit') - (float) $journalEntries->sum('credit'))) < 0.001,
        'groups_match' => $serviceJournalGroups === $directJournalGroups,
        'coa_summary_match' => $serviceCoaSummary === $directCoaSummary,
        'group_count' => count($serviceJournalGroups),
        'coa_count' => count($serviceCoaSummary),
    ],
    'drill_down_financial_report' => [
        'count_match' => $drillService['count'] === $journalEntries->count(),
        'total_debit_match' => abs((float) $drillService['total_debit'] - (float) $journalEntries->sum('debit')) < 0.001,
        'total_credit_match' => abs((float) $drillService['total_credit'] - (float) $journalEntries->sum('credit')) < 0.001,
        'groups_match' => $serviceDrillGroups === $directDrillGroups,
        'group_count' => count($serviceDrillGroups),
    ],
    'cash_flow_april_snapshot' => [
        'sections_match' => $serviceCashSections === $directCashSections,
        'opening_balance_match' => abs((float) $cashService['opening_balance'] - $openingDirect) < 0.001,
        'net_change_match' => abs((float) $cashService['net_change'] - $cashNetDirect) < 0.001,
        'closing_balance_match' => abs((float) $cashService['closing_balance'] - $cashClosingDirect) < 0.001,
        'service_opening_balance' => (float) $cashService['opening_balance'],
        'service_net_change' => (float) $cashService['net_change'],
        'service_closing_balance' => (float) $cashService['closing_balance'],
        'direct_opening_balance' => $openingDirect,
        'direct_net_change' => $cashNetDirect,
        'direct_closing_balance' => $cashClosingDirect,
        'section_count' => count($serviceCashSections),
        'source_snapshot' => [
            'cash_bank_transactions' => [
                'count' => (int) CashBankTransaction::whereBetween('date', [$cashStart, $cashEnd])->count(),
                'amount_sum' => (float) CashBankTransaction::whereBetween('date', [$cashStart, $cashEnd])->sum('amount'),
            ],
            'assets' => [
                'count' => (int) Asset::whereBetween('purchase_date', [$cashStart, $cashEnd])->count(),
                'purchase_sum' => (float) Asset::whereBetween('purchase_date', [$cashStart, $cashEnd])->sum('purchase_cost'),
            ],
            'customer_receipts' => [
                'count' => (int) $receiptQuery->count(),
                'amount_sum' => (float) $receiptQuery->sum('amount'),
            ],
            'deposit_journals' => (int) JournalEntry::whereBetween('date', [$cashStart, $cashEnd])
                ->where('source_type', App\Models\Deposit::class)
                ->where('journal_type', 'deposit')
                ->count(),
            'transfer_journals' => (int) JournalEntry::whereBetween('date', [$cashStart, $cashEnd])
                ->where('source_type', App\Models\CashBankTransfer::class)
                ->where('journal_type', 'transfer')
                ->count(),
            'vendor_payment_journals' => (int) JournalEntry::whereBetween('date', [$cashStart, $cashEnd])
                ->where('source_type', App\Models\VendorPayment::class)
                ->where('journal_type', 'payment')
                ->count(),
        ],
    ],
    'profit_loss_multi_division' => $profitLossDirect,
    'inventory_card_april_snapshot' => $inventoryDirect,
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;