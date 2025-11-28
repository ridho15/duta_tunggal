<?php

namespace App\Services\Reports;

use App\Models\Asset;
use App\Models\CashBankTransaction;
use App\Models\CustomerReceiptItem;
use App\Models\Reports\CashFlowCashAccount;
use App\Models\Reports\CashFlowItem;
use App\Models\Reports\CashFlowSection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CashFlowReportService
{
    private const CASH_IN_TYPES = ['cash_in', 'bank_in'];
    private const CASH_OUT_TYPES = ['cash_out', 'bank_out'];

    /**
     * Runtime filters injected from the report UI.
     */
    private array $filters = [];

    private ?array $cashAccountPrefixes = null;

    /**
     * Generate cash flow data for a period using the direct or indirect method.
     */
    public function generate(?string $startDate = null, ?string $endDate = null, array $filters = []): array
    {
        $method = $filters['method'] ?? 'direct';
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();
        $this->filters = $filters;

        if ($method === 'indirect') {
            return $this->generateIndirectMethod($start, $end);
        }

        // Default to direct method
        return $this->generateDirectMethod($start, $end);
    }

    /**
     * Generate cash flow data using the direct method.
     */
    private function generateDirectMethod(Carbon $start, Carbon $end): array
    {
        $sections = CashFlowSection::query()
            ->with(['items' => function ($query) {
                $query->with(['prefixes', 'sources'])
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get()
            ->map(function (CashFlowSection $section) use ($start, $end) {
                return $this->buildSection($section, $start, $end);
            })
            ->values();

        $netChange = round($sections->sum('total'), 2);
        $openingBalance = $this->getOpeningBalance($start);
        $closingBalance = round($openingBalance + $netChange, 2);

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'method' => 'direct',
            'sections' => $sections->toArray(),
            'net_change' => $netChange,
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => $closingBalance,
        ];
    }

    /**
     * Generate cash flow data using the indirect method.
     */
    private function generateIndirectMethod(Carbon $start, Carbon $end): array
    {
        // Get net income from income statement
        $incomeStatement = app(\App\Services\IncomeStatementService::class)->generate([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'cabang_id' => $this->filters['branches'] ?? null,
        ]);

        $netIncome = $incomeStatement['net_profit'] ?? 0;

        // Operating Activities Section (Indirect Method)
        $operatingActivities = [
            'key' => 'operating',
            'label' => 'Aktivitas Operasi',
            'items' => [
                [
                    'key' => 'net_income',
                    'label' => 'Laba Bersih',
                    'amount' => round($netIncome, 2),
                    'metadata' => [],
                ],
            ],
            'total' => round($netIncome, 2),
        ];

        // Add back non-cash expenses and adjust for working capital changes
        $adjustments = $this->calculateIndirectAdjustments($start, $end);
        foreach ($adjustments as $adjustment) {
            $operatingActivities['items'][] = $adjustment;
            $operatingActivities['total'] += $adjustment['amount'];
        }

        $operatingActivities['total'] = round($operatingActivities['total'], 2);

        // For now, keep investing and financing sections simple (could be enhanced later)
        $investingActivities = [
            'key' => 'investing',
            'label' => 'Aktivitas Investasi',
            'items' => [],
            'total' => 0,
        ];

        $financingActivities = [
            'key' => 'financing',
            'label' => 'Aktivitas Pendanaan',
            'items' => [],
            'total' => 0,
        ];

        $sections = [$operatingActivities, $investingActivities, $financingActivities];
        $netChange = round($sections[0]['total'] + $sections[1]['total'] + $sections[2]['total'], 2);
        $openingBalance = $this->getOpeningBalance($start);
        $closingBalance = round($openingBalance + $netChange, 2);

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'method' => 'indirect',
            'sections' => $sections,
            'net_change' => $netChange,
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => $closingBalance,
        ];
    }

    /**
     * Calculate adjustments for indirect method cash flow.
     */
    private function calculateIndirectAdjustments(Carbon $start, Carbon $end): array
    {
        $adjustments = [];

        // Add back depreciation expense (non-cash)
        $depreciationExpense = $this->sumJournalByPrefixes(['6311', '6312', '6313', '6314'], $start, $end, 'debit');
        if ($depreciationExpense > 0) {
            $adjustments[] = [
                'key' => 'add_depreciation',
                'label' => 'Penambahan Beban Penyusutan',
                'amount' => round($depreciationExpense, 2),
                'metadata' => [],
            ];
        }

        // Adjust for changes in accounts receivable (working capital)
        $arChange = $this->calculateWorkingCapitalChange('1120', $start, $end); // Piutang
        if ($arChange !== 0) {
            $adjustments[] = [
                'key' => 'ar_change',
                'label' => 'Penurunan/Penambahan Piutang',
                'amount' => round($arChange, 2),
                'metadata' => [],
            ];
        }

        // Adjust for changes in inventory (working capital)
        $inventoryChange = $this->calculateWorkingCapitalChange('1140', $start, $end); // Persediaan
        if ($inventoryChange !== 0) {
            $adjustments[] = [
                'key' => 'inventory_change',
                'label' => 'Penurunan/Penambahan Persediaan',
                'amount' => round($inventoryChange, 2),
                'metadata' => [],
            ];
        }

        // Adjust for changes in accounts payable (working capital)
        $apChange = $this->calculateWorkingCapitalChange('2110', $start, $end); // Hutang
        if ($apChange !== 0) {
            $adjustments[] = [
                'key' => 'ap_change',
                'label' => 'Kenaikan/Penurunan Hutang',
                'amount' => round($apChange, 2),
                'metadata' => [],
            ];
        }

        return $adjustments;
    }

    /**
     * Calculate working capital change for a COA prefix.
     */
    private function calculateWorkingCapitalChange(string $prefix, Carbon $start, Carbon $end): float
    {
        // Get beginning balance (end of previous period)
        $beginningBalance = $this->getAccountBalanceAtDate($prefix, $start->copy()->subDay());

        // Get ending balance
        $endingBalance = $this->getAccountBalanceAtDate($prefix, $end);

        // Change = Ending - Beginning
        // For cash flow, we want to see how this affects cash:
        // - Decrease in AR/AP = +cash (collected more receivables, paid more payables)
        // - Increase in AR/AP = -cash (extended more credit, received less payment)
        return $beginningBalance - $endingBalance;
    }

    /**
     * Get account balance at a specific date for given prefix.
     */
    private function getAccountBalanceAtDate(string $prefix, Carbon $date): float
    {
        $query = \App\Models\JournalEntry::query()
            ->join('chart_of_accounts', 'journal_entries.coa_id', '=', 'chart_of_accounts.id')
            ->where('chart_of_accounts.code', 'like', $prefix . '%')
            ->where('journal_entries.date', '<=', $date);

        $this->applyTransactionFilters($query);

        $debits = (float) $query->sum('journal_entries.debit');
        $credits = (float) $query->sum('journal_entries.credit');

        return $debits - $credits;
    }

    private function buildSection(CashFlowSection $section, Carbon $start, Carbon $end): array
    {
        $items = [];
        $total = 0.0;

        foreach ($section->items as $item) {
            $amount = $this->resolveAmount($item, $start, $end);
            $metadata = $this->buildMetadata($item, $start, $end);

            if ($item->include_assets) {
                $assetAdjustment = $this->sumAssetsByPrefixes(
                    $item->prefixes->where('is_asset', true)->pluck('prefix')->toArray(),
                    $start,
                    $end,
                    $item->type
                );

                if ($assetAdjustment !== 0.0) {
                    $amount += $assetAdjustment;
                    $metadata['asset_adjustment'] = round($assetAdjustment, 2);
                }
            }

            $amount = round($amount, 2);
            $total += $amount;

            $items[] = [
                'key' => $item->key,
                'label' => $item->label,
                'amount' => $amount,
                'metadata' => $metadata,
            ];
        }

        return [
            'key' => $section->key,
            'label' => $section->label,
            'items' => $items,
            'total' => round($total, 2),
        ];
    }

    private function resolveAmount(CashFlowItem $item, Carbon $start, Carbon $end): float
    {
        return match ($item->resolver) {
            'salesReceipts' => $this->sumSalesReceipts($start, $end),
            default => $this->sumCashBankByConfig($item, $start, $end),
        };
    }

    private function sumSalesReceipts(Carbon $start, Carbon $end): float
    {
        $receipts = CustomerReceiptItem::query()
            ->where(function (Builder $query) use ($start, $end) {
                $query->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function (Builder $nested) use ($start, $end) {
                        $nested->whereNull('payment_date')
                            ->whereHas('customerReceipt', function (Builder $receiptQuery) use ($start, $end) {
                                $receiptQuery->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()]);
                            });
                    });
            })
            ->whereHas('customerReceipt', function (Builder $query) {
                $query->whereIn('payment_method', ['Cash', 'Bank', 'Bank Transfer', 'Deposit'])
                    ->whereIn('status', ['Paid', 'paid', 'Partial', 'partial']);
            })
            ->sum('amount');

        return (float) $receipts;
    }

    private function sumCashBankByConfig(CashFlowItem $item, Carbon $start, Carbon $end): float
    {
        $prefixes = $item->prefixes->where('is_asset', false)->pluck('prefix')->toArray();
        if (empty($prefixes)) {
            return 0.0;
        }

        $cashBankAmount = match ($item->type) {
            'inflow' => $this->sumCashBankByPrefixes($prefixes, $start, $end, self::CASH_IN_TYPES),
            'outflow' => -1 * $this->sumCashBankByPrefixes($prefixes, $start, $end, self::CASH_OUT_TYPES),
            'net' => $this->sumCashBankByPrefixes($prefixes, $start, $end, self::CASH_IN_TYPES)
                - $this->sumCashBankByPrefixes($prefixes, $start, $end, self::CASH_OUT_TYPES),
            default => 0.0,
        };

        // Also include journal entries from transfers for cash flow
        $journalAmount = $this->sumJournalByPrefixes($prefixes, $start, $end, $item->type);

        return $cashBankAmount + $journalAmount;
    }

    private function sumCashBankByPrefixes(array $prefixes, Carbon $start, Carbon $end, array $types): float
    {
        if (empty($prefixes)) {
            return 0.0;
        }

        $query = CashBankTransaction::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('type', $types)
            ->whereHas('offsetCoa', function (Builder $query) use ($prefixes) {
                $query->where(function (Builder $inner) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        $inner->orWhere('code', 'like', $prefix . '%');
                    }
                });
            });

        $sum = $this->applyTransactionFilters($query)->sum('amount');

        return (float) $sum;
    }

    private function sumAssetsByPrefixes(array $prefixes, Carbon $start, Carbon $end, string $type): float
    {
        if (empty($prefixes)) {
            return 0.0;
        }

        $sum = Asset::query()
            ->whereBetween('purchase_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('assetCoa', function (Builder $query) use ($prefixes) {
                $query->where(function (Builder $inner) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        $inner->orWhere('code', 'like', $prefix . '%');
                    }
                });
            })
            ->sum('purchase_cost');

        $sum = (float) $sum;

        return match ($type) {
            'inflow' => $sum,
            'net' => -1 * $sum,
            default => -1 * $sum,
        };
    }

    private function buildMetadata(CashFlowItem $item, Carbon $start, Carbon $end): array
    {
        $sources = $item->sources
            ->sortBy('sort_order')
            ->pluck('label')
            ->values()
            ->toArray();

        $metadata = [
            'sources' => $sources,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
        ];

        $operationalPrefixes = $item->prefixes->where('is_asset', false)->pluck('prefix')->toArray();
        if (!empty($operationalPrefixes)) {
            $metadata['breakdown'] = [
                'inflow' => $this->getCashBankBreakdown($operationalPrefixes, $start, $end, self::CASH_IN_TYPES),
                'outflow' => $this->getCashBankBreakdown($operationalPrefixes, $start, $end, self::CASH_OUT_TYPES),
            ];
        }

        if ($item->resolver === 'salesReceipts') {
            $metadata['detail'] = $this->getSalesReceiptBreakdown($start, $end);
        }

        return $metadata;
    }

    private function getCashBankBreakdown(array $prefixes, Carbon $start, Carbon $end, array $types): array
    {
        if (empty($prefixes)) {
            return [];
        }

        $records = $this->applyTransactionFilters(
            CashBankTransaction::query()
                ->with('offsetCoa')
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->whereIn('type', $types)
                ->whereHas('offsetCoa', function (Builder $query) use ($prefixes) {
                    $query->where(function (Builder $inner) use ($prefixes) {
                        foreach ($prefixes as $prefix) {
                            $inner->orWhere('code', 'like', $prefix . '%');
                        }
                    });
                })
                ->selectRaw('offset_coa_id, SUM(amount) as total')
                ->groupBy('offset_coa_id')
        )->get();

        return $records->map(function (CashBankTransaction $transaction) {
            $coa = $transaction->offsetCoa;

            return [
                'coa_code' => $coa?->code,
                'coa_name' => $coa?->name,
                'amount' => round((float) $transaction->total, 2),
            ];
        })->values()->toArray();
    }

    private function getSalesReceiptBreakdown(Carbon $start, Carbon $end): array
    {
        return CustomerReceiptItem::query()
            ->with(['customerReceipt'])
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('customerReceipt', function (Builder $query) {
                $query->whereIn('payment_method', ['Cash', 'Bank', 'Bank Transfer', 'Deposit'])
                    ->whereIn('status', ['Paid', 'paid', 'Partial', 'partial']);
            })
            ->selectRaw('customer_receipt_id, SUM(amount) as total')
            ->groupBy('customer_receipt_id')
            ->get()
            ->map(function (CustomerReceiptItem $item) {
                $receipt = $item->customerReceipt;

                return [
                    'receipt_number' => $receipt?->receipt_number,
                    'customer_name' => $receipt?->customer?->name,
                    'amount' => round((float) $item->total, 2),
                ];
            })
            ->values()
            ->toArray();
    }

    private function applyTransactionFilters(Builder $query): Builder
    {
        if (!empty($this->filters['division_id'])) {
            $query->where('division_id', $this->filters['division_id']);
        }

        if (!empty($this->filters['project_id'])) {
            $query->where('project_id', $this->filters['project_id']);
        }

        if (!empty($this->filters['branches'])) {
            $query->whereIn('cabang_id', (array) $this->filters['branches']);
        }

        if (!empty($this->filters['cash_accounts'])) {
            $query->whereIn('cash_bank_account_id', (array) $this->filters['cash_accounts']);
        }

        return $query;
    }

    private function getOpeningBalance(Carbon $start): float
    {
        $cashBalance = $this->getCashBankOpeningBalance($start);
            $prefixedBalance = 0;
            // opening_balance column may not exist in older schemas; guard with schema check
            if (\Illuminate\Support\Facades\Schema::hasColumn('report_cash_flow_cash_accounts', 'opening_balance')) {
                $prefixedBalance = CashFlowCashAccount::query()
                    ->when($this->filters['cash_accounts'] ?? null, function ($query, $ids) {
                        $query->whereIn('id', (array) $ids);
                    })
                    ->sum('opening_balance');
            }

        return round($cashBalance + (float) $prefixedBalance, 2);
    }

    private function getCashBankOpeningBalance(Carbon $start): float
    {
        $prefixes = $this->getCashAccountPrefixes();
        if (empty($prefixes)) {
            return 0.0;
        }

        $baseQuery = CashBankTransaction::query()
            ->where('date', '<', $start->toDateString())
            ->when(!empty($this->filters['cash_accounts']), function (Builder $query) {
                $query->whereIn('cash_bank_account_id', (array) $this->filters['cash_accounts']);
            })
            ->whereHas('accountCoa', function (Builder $query) use ($prefixes) {
                $query->where(function (Builder $inner) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        $inner->orWhere('code', 'like', $prefix . '%');
                    }
                });
            });

        $inflow = (clone $baseQuery)->whereIn('type', self::CASH_IN_TYPES)->sum('amount');
        $outflow = (clone $baseQuery)->whereIn('type', self::CASH_OUT_TYPES)->sum('amount');

        $cashBankBalance = (float) $inflow - (float) $outflow;

        // Also include journal entries from transfers for opening balance
        $journalQuery = \App\Models\JournalEntry::query()
            ->where('date', '<', $start->toDateString())
            ->where('source_type', \App\Models\CashBankTransfer::class)
            ->where('journal_type', 'transfer')
            ->whereHas('coa', function (\Illuminate\Database\Eloquent\Builder $query) use ($prefixes) {
                $query->where(function (\Illuminate\Database\Eloquent\Builder $inner) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        $inner->orWhere('code', 'like', $prefix . '%');
                    }
                });
            });

        $journalDebit = (clone $journalQuery)->sum('debit');
        $journalCredit = (clone $journalQuery)->sum('credit');
        $journalBalance = $journalDebit - $journalCredit;

        return $cashBankBalance + $journalBalance;
    }

    private function getCashAccountPrefixes(): array
    {
        if ($this->cashAccountPrefixes !== null) {
            return $this->cashAccountPrefixes;
        }

            $prefixes = CashFlowCashAccount::query()
                ->when($this->filters['cash_accounts'] ?? null, function ($query, $ids) {
                    $query->whereIn('id', (array) $ids);
                })
                ->pluck('prefix')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $this->cashAccountPrefixes = $prefixes;

        return $prefixes;
    }

    private function sumJournalByPrefixes(array $prefixes, Carbon $start, Carbon $end, string $type): float
    {
        if (empty($prefixes)) {
            return 0.0;
        }

        // Sum journal entries for transfers (source_type = CashBankTransfer)
        $query = \App\Models\JournalEntry::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('source_type', \App\Models\CashBankTransfer::class)
            ->where('journal_type', 'transfer')
            ->whereHas('coa', function (\Illuminate\Database\Eloquent\Builder $query) use ($prefixes) {
                $query->where(function (\Illuminate\Database\Eloquent\Builder $inner) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        $inner->orWhere('code', 'like', $prefix . '%');
                    }
                });
            });

        $debit = (clone $query)->sum('debit');
        $credit = (clone $query)->sum('credit');

        // For cash flow, we need to determine if this is inflow or outflow based on account type
        // Expense accounts (debit balances) represent outflows
        // Revenue accounts (credit balances) represent inflows
        $accounts = \App\Models\ChartOfAccount::where(function ($q) use ($prefixes) {
            foreach ($prefixes as $prefix) {
                $q->orWhere('code', 'like', $prefix . '%');
            }
        })->get();

        $isExpenseAccount = $accounts->contains(function ($coa) {
            return in_array($coa->type, ['Expense']);
        });

        $isRevenueAccount = $accounts->contains(function ($coa) {
            return in_array($coa->type, ['Revenue']);
        });

        if ($isExpenseAccount) {
            // Expense accounts: debits represent cash outflows
            $amount = $debit;
            return match ($type) {
                'inflow' => 0,
                'outflow' => -$amount,  // negative for cash outflow
                'net' => -$amount,      // negative for net cash flow
                default => 0.0,
            };
        } elseif ($isRevenueAccount) {
            // Revenue accounts: credits represent cash inflows
            $amount = $credit;
            return match ($type) {
                'inflow' => $amount,   // positive for cash inflow
                'outflow' => 0,
                'net' => $amount,      // positive for net cash flow
                default => 0.0,
            };
        }

        // For other account types, use net calculation
        $netAmount = $debit - $credit;
        return match ($type) {
            'inflow' => $netAmount > 0 ? $netAmount : 0,
            'outflow' => $netAmount < 0 ? abs($netAmount) : 0,
            'net' => $netAmount,
            default => 0.0,
        };
    }
}

