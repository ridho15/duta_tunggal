<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service untuk menghitung Neraca Saldo (Trial Balance)
 *
 * Trial Balance menampilkan per akun:
 *  - Saldo Awal  (Beginning Balance)  : opening_balance + sum journal SEBELUM start_date
 *  - Mutasi Debit   periode (Debit)
 *  - Mutasi Kredit  periode (Credit)
 *  - Saldo Akhir (Ending Balance)      : saldo_awal ± mutasi periode tergantung normal balance
 */
class TrialBalanceService
{
    /**
     * Generate Trial Balance
     *
     * @param array $filters  ['start_date', 'end_date', 'cabang_id', 'show_zero_balance']
     * @return array
     */
    public function generate(array $filters = []): array
    {
        $startDate       = $filters['start_date']       ?? now()->startOfYear()->format('Y-m-d');
        $endDate         = $filters['end_date']         ?? now()->format('Y-m-d');
        $cabangId        = $filters['cabang_id']        ?? null;
        $showZeroBalance = $filters['show_zero_balance'] ?? false;

        // Fetch all active accounts ordered by code
        $accounts = ChartOfAccount::where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get();

        $accountIds = $accounts->pluck('id');

        // --- Beginning balances (all journal entries BEFORE start_date) ---
        $beginningMap = $accountIds->isEmpty()
            ? collect()
            : JournalEntry::whereIn('coa_id', $accountIds)
                ->whereDate('date', '<', $startDate)
                ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                ->groupBy('coa_id')
                ->selectRaw('coa_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->get()
                ->keyBy('coa_id');

        // --- Period movements (within start_date .. end_date) ---
        $periodMap = $accountIds->isEmpty()
            ? collect()
            : JournalEntry::whereIn('coa_id', $accountIds)
                ->whereDate('date', '>=', $startDate)
                ->whereDate('date', '<=', $endDate)
                ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                ->groupBy('coa_id')
                ->selectRaw('coa_id, SUM(debit) as period_debit, SUM(credit) as period_credit')
                ->get()
                ->keyBy('coa_id');

        // Build enriched account rows
        $rows = $accounts->map(function (ChartOfAccount $account) use ($beginningMap, $periodMap) {
            $opening = (float) ($account->opening_balance ?? 0);

            $bRow = $beginningMap->get($account->id);
            $preBegDebit  = (float) ($bRow->total_debit  ?? 0);
            $preBegCredit = (float) ($bRow->total_credit ?? 0);

            $pRow = $periodMap->get($account->id);
            $periodDebit  = (float) ($pRow->period_debit  ?? 0);
            $periodCredit = (float) ($pRow->period_credit ?? 0);

            // Normal balance direction
            $isDebitNormal = in_array($account->type, ['Asset', 'Expense'], true);

            // Beginning balance  = opening + pre-period journal movement
            $beginningBalance = $isDebitNormal
                ? $opening + $preBegDebit - $preBegCredit
                : $opening - $preBegDebit + $preBegCredit;

            // Ending balance = beginning + period movement
            $endingBalance = $isDebitNormal
                ? $beginningBalance + $periodDebit - $periodCredit
                : $beginningBalance - $periodDebit + $periodCredit;

            return (object) [
                'id'                => $account->id,
                'parent_id'         => $account->parent_id,
                'code'              => $account->code,
                'name'              => $account->name,
                'type'              => $account->type,
                'normal_balance'    => $isDebitNormal ? 'D' : 'C',
                'beginning_balance' => $beginningBalance,
                'period_debit'      => $periodDebit,
                'period_credit'     => $periodCredit,
                'ending_balance'    => $endingBalance,
                'is_parent'         => false, // will be resolved below
            ];
        });

        // Mark parent accounts (those that have children)
        $parentIds = $accounts->pluck('parent_id')->filter()->unique();
        $rows = $rows->map(function ($row) use ($parentIds) {
            $row->is_parent = $parentIds->contains($row->id);
            return $row;
        });

        // Optionally filter zero balance rows
        if (!$showZeroBalance) {
            $rows = $rows->filter(function ($row) {
                return abs($row->beginning_balance) > 0.001
                    || abs($row->period_debit)      > 0.001
                    || abs($row->period_credit)     > 0.001
                    || abs($row->ending_balance)    > 0.001;
            });
        }

        // Order hierarchically (parents before their children)
        $ordered = $this->orderHierarchically($rows);

        // --- Grand Totals ---
        $grandBeginning = $ordered->sum('beginning_balance');
        $grandDebit     = $ordered->sum('period_debit');
        $grandCredit    = $ordered->sum('period_credit');
        $grandEnding    = $ordered->sum('ending_balance');

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ],
            'rows'          => $ordered->values(),
            'grand_totals'  => [
                'beginning_balance' => $grandBeginning,
                'period_debit'      => $grandDebit,
                'period_credit'     => $grandCredit,
                'ending_balance'    => $grandEnding,
            ],
        ];
    }

    /**
     * Sort rows so each parent appears immediately before its children (depth-first).
     */
    protected function orderHierarchically(Collection $rows): Collection
    {
        $byParent = $rows->groupBy(fn ($r) => $r->parent_id ?? 0);
        $ordered  = collect();
        $visited  = [];

        $walk = function ($parentId) use (&$walk, $byParent, &$ordered, &$visited) {
            $children = ($byParent->get($parentId) ?? collect())
                ->sortBy('code')
                ->values();

            foreach ($children as $row) {
                if (isset($visited[$row->id])) {
                    continue;
                }
                $visited[$row->id] = true;
                $ordered->push($row);
                $walk($row->id);
            }
        };

        $walk(0);

        // Append orphaned rows (parent_id set but parent not in result set)
        foreach ($rows as $row) {
            if (!isset($visited[$row->id])) {
                $ordered->push($row);
            }
        }

        return $ordered;
    }

    /**
     * Format a numeric value as Indonesian currency string.
     */
    public static function formatAmount(float $value): string
    {
        return number_format(abs($value), 2, ',', '.');
    }
}
