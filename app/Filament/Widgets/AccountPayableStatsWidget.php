<?php

namespace App\Filament\Widgets;

use App\Models\AccountPayable;
use Filament\Widgets\StatsOverviewWidget;
use Livewire\Livewire;

class AccountPayableStatsWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = null;
    
    protected function getStats(): array
    {
        // Get the current page instance to access filters
        $livewire = Livewire::current();
        $tableFilters = $livewire->tableFilters ?? [];
        
        // Build query with current filters
        $query = AccountPayable::query();
        
        if (!empty($tableFilters)) {
            // Apply supplier filter
            if (isset($tableFilters['supplier_id']['values']) && !empty($tableFilters['supplier_id']['values'])) {
                $query->whereIn('supplier_id', $tableFilters['supplier_id']['values']);
            }
            
            // Apply status filter
            if (isset($tableFilters['status']['values']) && !empty($tableFilters['status']['values'])) {
                $query->whereIn('status', $tableFilters['status']['values']);
            }
            
            // Apply amount range filter
            if (isset($tableFilters['amount_range']) && !empty($tableFilters['amount_range'])) {
                $data = $tableFilters['amount_range'];
                if (isset($data['amount_from']) && $data['amount_from'] !== null) {
                    $query->where('total', '>=', $data['amount_from']);
                }
                if (isset($data['amount_to']) && $data['amount_to'] !== null) {
                    $query->where('total', '<=', $data['amount_to']);
                }
            }
            
            // Apply outstanding filter
            if (isset($tableFilters['outstanding_only']['isActive']) && $tableFilters['outstanding_only']['isActive']) {
                $query->where('remaining', '>', 0);
            }
            
            // Apply overdue filter
            if (isset($tableFilters['overdue']['isActive']) && $tableFilters['overdue']['isActive']) {
                $query->whereHas('invoice', function ($q) {
                    $q->where('due_date', '<', now());
                })->where('status', 'Belum Lunas');
            }
            
            // Apply date range filter
            if (isset($tableFilters['date_range']) && !empty($tableFilters['date_range'])) {
                $data = $tableFilters['date_range'];
                if (isset($data['created_from']) && $data['created_from'] !== null) {
                    $query->whereDate('created_at', '>=', $data['created_from']);
                }
                if (isset($data['created_until']) && $data['created_until'] !== null) {
                    $query->whereDate('created_at', '<=', $data['created_until']);
                }
            }
            
            // Apply due date range filter
            if (isset($tableFilters['due_date_range']) && !empty($tableFilters['due_date_range'])) {
                $data = $tableFilters['due_date_range'];
                $query->whereHas('invoice', function ($q) use ($data) {
                    if (isset($data['due_from']) && $data['due_from'] !== null) {
                        $q->whereDate('due_date', '>=', $data['due_from']);
                    }
                    if (isset($data['due_until']) && $data['due_until'] !== null) {
                        $q->whereDate('due_date', '<=', $data['due_until']);
                    }
                });
            }
            
            // Apply overdue days filter
            if (isset($tableFilters['overdue_days']['value']) && !empty($tableFilters['overdue_days']['value'])) {
                $value = $tableFilters['overdue_days']['value'];
                $query->whereHas('invoice', function ($q) use ($value) {
                    $now = now();
                    switch ($value) {
                        case '1-30':
                            $q->whereBetween('due_date', [$now->copy()->subDays(30), $now->copy()->subDay()]);
                            break;
                        case '31-60':
                            $q->whereBetween('due_date', [$now->copy()->subDays(60), $now->copy()->subDays(31)]);
                            break;
                        case '60+':
                            $q->where('due_date', '<', $now->copy()->subDays(60));
                            break;
                    }
                })->where('status', 'Belum Lunas');
            }
        }
        
        // Calculate totals based on filtered data
        $totals = $query->selectRaw('
            SUM(total) as total_amount,
            SUM(paid) as paid_amount, 
            SUM(remaining) as remaining_amount,
            COUNT(*) as record_count
        ')->first();
        
        return [
            StatsOverviewWidget\Stat::make('Total Amount', 'Rp ' . number_format($totals->total_amount ?? 0, 0, ',', '.'))
                ->description($totals->record_count . ' records')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
                
            StatsOverviewWidget\Stat::make('Paid Amount', 'Rp ' . number_format($totals->paid_amount ?? 0, 0, ',', '.'))
                ->description('Already paid')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            StatsOverviewWidget\Stat::make('Outstanding', 'Rp ' . number_format($totals->remaining_amount ?? 0, 0, ',', '.'))
                ->description('Remaining to pay')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
