<?php

namespace App\Services\Reports;

use App\Models\Cabang;
use App\Services\BalanceSheetService;
use App\Services\IncomeStatementService;
use Illuminate\Support\Carbon;

class FinancialStatementReportService
{
    public function __construct(
        protected IncomeStatementService $incomeStatementService,
        protected BalanceSheetService $balanceSheetService,
        protected HppReportService $hppReportService,
    ) {
    }

    public function generate(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $filters['end_date'] ?? now()->endOfMonth()->toDateString();
        $cabangId = blank($filters['cabang_id'] ?? null) ? null : (int) $filters['cabang_id'];
        $statementType = $this->normalizeStatementType($filters['statement_type'] ?? 'all');

        $report = [
            'statement_type' => $statementType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period_label' => $this->periodLabel($startDate, $endDate),
            'branch_name' => $cabangId ? Cabang::query()->whereKey($cabangId)->value('nama') : null,
            'pl' => null,
            'bs' => null,
            'cogm' => null,
        ];

        if (in_array($statementType, ['all', 'pl'], true)) {
            $report['pl'] = $this->buildProfitAndLossPayload(
                $this->incomeStatementService->generate([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'cabang_id' => $cabangId,
                ]),
                $startDate,
                $endDate,
            );
        }

        if (in_array($statementType, ['all', 'bs'], true)) {
            $report['bs'] = $this->balanceSheetService->generate([
                'as_of_date' => $endDate,
                'cabang_id' => $cabangId,
                'display_level' => 'all',
                'show_zero_balance' => true,
            ]);
        }

        if (in_array($statementType, ['all', 'cogm'], true)) {
            $report['cogm'] = $this->buildCogmPayload(
                $this->hppReportService->generate($startDate, $endDate, [
                    'branches' => $cabangId ? [$cabangId] : [],
                ])
            );
        }

        return $report;
    }

    protected function buildProfitAndLossPayload(array $report, string $startDate, string $endDate): array
    {
        return [
            'revenue' => (float) ($report['revenue']['total'] ?? 0),
            'expense' => (float) ($report['expense']['total'] ?? 0),
            'cogs' => (float) ($report['cogs']['total'] ?? 0),
            'gross_profit' => (float) ($report['gross_profit'] ?? 0),
            'opex' => (float) ($report['operating_expenses']['total'] ?? 0),
            'operating_profit' => (float) ($report['operating_profit'] ?? 0),
            'other_net' => (float) ($report['net_other_income_expense'] ?? 0),
            'profit_before_tax' => (float) ($report['profit_before_tax'] ?? 0),
            'tax' => (float) ($report['tax_expense']['total'] ?? 0),
            'net_profit' => (float) ($report['net_profit'] ?? 0),
            'period' => $this->periodLabel($startDate, $endDate),
            'sales_revenue_accounts' => $this->normalizeAccounts($report['sales_revenue']['accounts'] ?? []),
            'cogs_accounts' => $this->normalizeAccounts($report['cogs']['accounts'] ?? []),
            'operating_expense_accounts' => $this->normalizeAccounts($report['operating_expenses']['accounts'] ?? []),
            'other_income_accounts' => $this->normalizeAccounts($report['other_income']['accounts'] ?? []),
            'other_expense_accounts' => $this->normalizeAccounts($report['other_expense']['accounts'] ?? []),
            'tax_accounts' => $this->normalizeAccounts($report['tax_expense']['accounts'] ?? []),
        ];
    }

    protected function buildCogmPayload(array $report): array
    {
        return [
            'raw_materials' => [
                'opening' => (float) data_get($report, 'raw_materials.opening', 0),
                'purchases' => (float) data_get($report, 'raw_materials.purchases', 0),
                'available' => (float) data_get($report, 'raw_materials.available', 0),
                'closing' => (float) data_get($report, 'raw_materials.closing', 0),
                'used' => (float) data_get($report, 'raw_materials.used', 0),
            ],
            'direct_labor' => (float) ($report['direct_labor'] ?? 0),
            'overhead' => [
                'items' => collect($report['overhead']['items'] ?? [])->map(function ($item) {
                    return [
                        'label' => (string) ($item['label'] ?? ''),
                        'amount' => (float) ($item['amount'] ?? 0),
                    ];
                })->values()->all(),
                'total' => (float) data_get($report, 'overhead.total', 0),
            ],
            'wip' => [
                'opening' => (float) data_get($report, 'wip.opening', 0),
                'closing' => (float) data_get($report, 'wip.closing', 0),
            ],
            'production_cost' => (float) ($report['production_cost'] ?? 0),
            'cogm' => (float) ($report['cogm'] ?? 0),
        ];
    }

    protected function normalizeAccounts(iterable $accounts): array
    {
        return collect($accounts)
            ->map(function ($account) {
                if (is_array($account)) {
                    return [
                        'code' => $account['code'] ?? '',
                        'name' => $account['name'] ?? '',
                        'balance' => (float) ($account['balance'] ?? 0),
                    ];
                }

                return [
                    'code' => (string) ($account->code ?? ''),
                    'name' => (string) ($account->name ?? ''),
                    'balance' => (float) ($account->balance ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    protected function normalizeStatementType(string $statementType): string
    {
        return in_array($statementType, ['all', 'pl', 'bs', 'cogm'], true) ? $statementType : 'all';
    }

    protected function periodLabel(string $startDate, string $endDate): string
    {
        return Carbon::parse($startDate)->format('d M Y') . ' s/d ' . Carbon::parse($endDate)->format('d M Y');
    }
}
