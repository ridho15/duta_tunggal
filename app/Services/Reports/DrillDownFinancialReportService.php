<?php

namespace App\Services\Reports;

use App\Models\JournalEntry;
use Illuminate\Support\Carbon;

class DrillDownFinancialReportService
{
    public function generate(array $filters = []): array
    {
        $start = Carbon::parse($filters['start_date'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $end = Carbon::parse($filters['end_date'] ?? now()->endOfMonth()->toDateString())->endOfDay();
        $coaId = isset($filters['coa_id']) && $filters['coa_id'] !== '' ? (int) $filters['coa_id'] : null;
        $accountType = $filters['account_type'] ?? null;
        $cabangId = isset($filters['cabang_id']) && $filters['cabang_id'] !== '' ? (int) $filters['cabang_id'] : null;

        $query = JournalEntry::query()
            ->with('coa')
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('id');

        if ($coaId) {
            $query->where('coa_id', $coaId);
        } elseif ($accountType) {
            $query->whereHas('coa', fn ($coaQuery) => $coaQuery->where('type', $accountType));
        }

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $entries = $query->get();
        $totalDebit = (float) $entries->sum('debit');
        $totalCredit = (float) $entries->sum('credit');

        $grouped = $entries->groupBy('coa_id')->map(function ($lines) {
            $coa = $lines->first()?->coa;
            $totalDebit = (float) $lines->sum('debit');
            $totalCredit = (float) $lines->sum('credit');

            return [
                'coa' => $coa,
                'lines' => $lines->values(),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'balance' => in_array(optional($coa)->type, ['Asset', 'Expense'], true)
                    ? $totalDebit - $totalCredit
                    : $totalCredit - $totalDebit,
            ];
        })->sortBy(fn (array $row) => $row['coa']?->code ?? 'zzz')->values()->all();

        return [
            'grouped' => $grouped,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'count' => $entries->count(),
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'coa_id' => $coaId,
                'account_type' => $accountType,
                'cabang_id' => $cabangId,
            ],
            'period' => $start->format('d M Y') . ' s/d ' . $end->format('d M Y'),
        ];
    }
}