<?php

namespace App\Services\Reports;

use App\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class JournalConsolidationReportService
{
    public function generate(array $filters = []): array
    {
        $start = Carbon::parse($filters['start_date'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $end = Carbon::parse($filters['end_date'] ?? now()->endOfMonth()->toDateString())->endOfDay();
        $branchIds = collect($filters['branch_ids'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();
        $journalType = $filters['journal_type'] ?? null;
        $groupByBranch = filter_var($filters['group_by_branch'] ?? true, FILTER_VALIDATE_BOOL);

        $query = JournalEntry::query()
            ->with(['coa', 'cabang'])
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('transaction_id')
            ->orderBy('id');

        if ($branchIds !== []) {
            $query->whereIn('cabang_id', $branchIds);
        }

        if ($journalType !== null && $journalType !== '') {
            $query->where('journal_type', $journalType);
        }

        $entries = $query->get();
        $totalDebit = (float) $entries->sum('debit');
        $totalCredit = (float) $entries->sum('credit');
        $difference = $totalDebit - $totalCredit;

        return [
            'grouped' => $this->buildGroups($entries, $groupByBranch, $totalDebit, $totalCredit, $difference),
            'coa_summary' => $this->buildCoaSummary($entries),
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'branch_ids' => $branchIds,
                'journal_type' => $journalType,
                'group_by_branch' => $groupByBranch,
            ],
            'count' => $entries->count(),
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'difference' => $difference,
            'balanced' => abs($difference) < 0.01,
            'period' => $start->format('d M Y') . ' s/d ' . $end->format('d M Y'),
        ];
    }

    private function buildGroups(Collection $entries, bool $groupByBranch, float $totalDebit, float $totalCredit, float $difference): array
    {
        if (! $groupByBranch) {
            return [[
                'cabang_id' => null,
                'cabang_name' => 'Semua Cabang (Konsolidasi)',
                'entries' => $entries->values(),
                'count' => $entries->count(),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'balance' => $difference,
            ]];
        }

        return $entries
            ->groupBy(fn (JournalEntry $entry) => $entry->cabang_id ?: 'uncategorized')
            ->map(function (Collection $lines, $cabangKey) {
                $first = $lines->first();
                $cabang = $first?->cabang;

                return [
                    'cabang_id' => is_numeric($cabangKey) ? (int) $cabangKey : null,
                    'cabang_name' => $cabang?->nama ?: 'Tanpa Cabang',
                    'entries' => $lines->values(),
                    'count' => $lines->count(),
                    'total_debit' => (float) $lines->sum('debit'),
                    'total_credit' => (float) $lines->sum('credit'),
                    'balance' => (float) $lines->sum('debit') - (float) $lines->sum('credit'),
                ];
            })
            ->sortBy('cabang_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function buildCoaSummary(Collection $entries): array
    {
        return $entries
            ->groupBy('coa_id')
            ->map(function (Collection $lines) {
                $coa = $lines->first()?->coa;

                return [
                    'coa' => $coa,
                    'total_debit' => (float) $lines->sum('debit'),
                    'total_credit' => (float) $lines->sum('credit'),
                    'balance' => (float) $lines->sum('debit') - (float) $lines->sum('credit'),
                ];
            })
            ->sortBy(fn (array $item) => $item['coa']?->code ?? 'zzz')
            ->values()
            ->all();
    }
}