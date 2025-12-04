<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JournalEntryAggregationService
{
    /**
     * Get journal entries grouped by parent COA with child details
     *
     * @param array $filters
     * @return Collection
     */
    public function getGroupedByParent(array $filters = []): Collection
    {
        $query = JournalEntry::with(['coa.coaParent', 'coa.children'])
            ->orderBy('date', 'desc');

        // Apply filters
        if (isset($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }

        if (isset($filters['journal_type'])) {
            $query->where('journal_type', $filters['journal_type']);
        }

        if (isset($filters['cabang_id'])) {
            $query->where('cabang_id', $filters['cabang_id']);
        }

        $journalEntries = $query->get();

        // Group by parent COA
        $groupedData = [];
        
        foreach ($journalEntries as $entry) {
            $coa = $entry->coa;
            
            // Determine parent: use coaParent if exists, otherwise the COA itself is the parent
            $parent = $coa->parent_id ? $coa->coaParent : $coa;
            $parentKey = 'parent_' . $parent->id;
            
            // Initialize parent if not exists
            if (!isset($groupedData[$parentKey])) {
                $groupedData[$parentKey] = [
                    'id' => $parent->id,
                    'code' => $parent->code,
                    'name' => $parent->name,
                    'type' => $parent->type,
                    'is_parent' => true,
                    'total_debit' => 0,
                    'total_credit' => 0,
                    'balance' => 0,
                    'children' => [],
                ];
            }
            
            // Add to parent totals
            $groupedData[$parentKey]['total_debit'] += $entry->debit;
            $groupedData[$parentKey]['total_credit'] += $entry->credit;
            
            // If COA has parent, it's a child entry
            if ($coa->parent_id) {
                $childKey = 'child_' . $coa->id;
                
                // Initialize child COA if not exists
                if (!isset($groupedData[$parentKey]['children'][$childKey])) {
                    $groupedData[$parentKey]['children'][$childKey] = [
                        'id' => $coa->id,
                        'code' => $coa->code,
                        'name' => $coa->name,
                        'type' => $coa->type,
                        'is_parent' => false,
                        'total_debit' => 0,
                        'total_credit' => 0,
                        'balance' => 0,
                        'entries' => [],
                    ];
                }
                
                // Add to child totals
                $groupedData[$parentKey]['children'][$childKey]['total_debit'] += $entry->debit;
                $groupedData[$parentKey]['children'][$childKey]['total_credit'] += $entry->credit;
                
                // Add entry to child
                $groupedData[$parentKey]['children'][$childKey]['entries'][] = [
                    'id' => $entry->id,
                    'date' => $entry->date,
                    'created_at' => $entry->created_at,
                    'reference' => $entry->reference,
                    'description' => $entry->description,
                    'debit' => $entry->debit,
                    'credit' => $entry->credit,
                    'journal_type' => $entry->journal_type,
                    'source_type' => $entry->source_type,
                    'source_id' => $entry->source_id,
                ];
            } else {
                // Entry directly on parent COA
                if (!isset($groupedData[$parentKey]['entries'])) {
                    $groupedData[$parentKey]['entries'] = [];
                }
                
                $groupedData[$parentKey]['entries'][] = [
                    'id' => $entry->id,
                    'date' => $entry->date,
                    'created_at' => $entry->created_at,
                    'reference' => $entry->reference,
                    'description' => $entry->description,
                    'debit' => $entry->debit,
                    'credit' => $entry->credit,
                    'journal_type' => $entry->journal_type,
                    'source_type' => $entry->source_type,
                    'source_id' => $entry->source_id,
                ];
            }
        }
        
        // Calculate balances for each group
        foreach ($groupedData as &$parentGroup) {
            // Calculate parent balance
            $parentGroup['balance'] = $this->calculateBalance(
                $parentGroup['type'],
                $parentGroup['total_debit'],
                $parentGroup['total_credit']
            );
            
            // Calculate child balances
            if (!empty($parentGroup['children'])) {
                foreach ($parentGroup['children'] as &$child) {
                    $child['balance'] = $this->calculateBalance(
                        $child['type'],
                        $child['total_debit'],
                        $child['total_credit']
                    );
                }
            }
        }
        
        return collect($groupedData)->values();
    }

    /**
     * Calculate balance based on account type
     *
     * @param string $type
     * @param float $debit
     * @param float $credit
     * @return float
     */
    protected function calculateBalance(string $type, float $debit, float $credit): float
    {
        return match ($type) {
            'Asset', 'Expense' => $debit - $credit,
            'Liability', 'Equity', 'Revenue', 'Contra Asset' => $credit - $debit,
            default => $debit - $credit,
        };
    }

    /**
     * Get summary statistics for journal entries
     *
     * @param array $filters
     * @return array
     */
    public function getSummary(array $filters = []): array
    {
        $query = JournalEntry::query();

        // Apply filters
        if (isset($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }

        if (isset($filters['journal_type'])) {
            $query->where('journal_type', $filters['journal_type']);
        }

        if (isset($filters['cabang_id'])) {
            $query->where('cabang_id', $filters['cabang_id']);
        }

        $summary = $query->select([
            DB::raw('COUNT(*) as total_entries'),
            DB::raw('SUM(debit) as total_debit'),
            DB::raw('SUM(credit) as total_credit'),
            DB::raw('SUM(debit) - SUM(credit) as net_balance'),
        ])->first();

        return [
            'total_entries' => $summary->total_entries ?? 0,
            'total_debit' => $summary->total_debit ?? 0,
            'total_credit' => $summary->total_credit ?? 0,
            'net_balance' => $summary->net_balance ?? 0,
            'is_balanced' => abs(($summary->total_debit ?? 0) - ($summary->total_credit ?? 0)) < 0.01,
        ];
    }

    /**
     * Get all parent COAs that have journal entries
     *
     * @param array $filters
     * @return Collection
     */
    public function getParentCoasWithEntries(array $filters = []): Collection
    {
        $query = DB::table('journal_entries')
            ->join('chart_of_accounts as coa', 'journal_entries.coa_id', '=', 'coa.id')
            ->leftJoin('chart_of_accounts as parent', 'coa.parent_id', '=', 'parent.id')
            ->select([
                DB::raw('COALESCE(parent.id, coa.id) as parent_id'),
                DB::raw('COALESCE(parent.code, coa.code) as parent_code'),
                DB::raw('COALESCE(parent.name, coa.name) as parent_name'),
                DB::raw('COUNT(DISTINCT journal_entries.id) as entry_count'),
                DB::raw('SUM(journal_entries.debit) as total_debit'),
                DB::raw('SUM(journal_entries.credit) as total_credit'),
            ]);

        // Apply filters
        if (isset($filters['start_date'])) {
            $query->where('journal_entries.date', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('journal_entries.date', '<=', $filters['end_date']);
        }

        if (isset($filters['journal_type'])) {
            $query->where('journal_entries.journal_type', $filters['journal_type']);
        }

        if (isset($filters['cabang_id'])) {
            $query->where('journal_entries.cabang_id', $filters['cabang_id']);
        }

        return $query->groupBy(DB::raw('COALESCE(parent.id, coa.id)'), 'parent_code', 'parent_name')
            ->orderBy('parent_code')
            ->get();
    }
}
