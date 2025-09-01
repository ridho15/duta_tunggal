<?php

namespace App\Filament\Resources\AccountReceivableResource\Pages;

use App\Filament\Resources\AccountReceivableResource;
use App\Filament\Widgets\AccountReceivableStatsWidget;
use App\Models\AccountReceivable;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAccountReceivables extends ListRecords
{
    protected static string $resource = AccountReceivableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }

    public function getTitle(): string
    {
        $totalAmount = $this->getFilteredQuery()->sum('remaining');
        return 'Account Receivable - RP. ' . number_format($totalAmount, 0, ',', '.');
    }

    protected function getFilteredQuery(): Builder
    {
        $query = AccountReceivable::query();
        
        // Apply active table filters
        $tableFilters = $this->tableFilters;
        
        if (!empty($tableFilters)) {
            // Apply customer filter if set
            if (isset($tableFilters['customer_id']['values']) && !empty($tableFilters['customer_id']['values'])) {
                $query->whereIn('customer_id', $tableFilters['customer_id']['values']);
            }
            
            // Apply status filter if set  
            if (isset($tableFilters['status']['values']) && !empty($tableFilters['status']['values'])) {
                $query->whereIn('status', $tableFilters['status']['values']);
            }
            
            // Apply amount range filter if set
            if (isset($tableFilters['amount_range']) && !empty($tableFilters['amount_range'])) {
                $data = $tableFilters['amount_range'];
                if (isset($data['amount_from']) && $data['amount_from'] !== null) {
                    $query->where('total', '>=', $data['amount_from']);
                }
                if (isset($data['amount_to']) && $data['amount_to'] !== null) {
                    $query->where('total', '<=', $data['amount_to']);
                }
            }
            
            // Apply outstanding only filter
            if (isset($tableFilters['outstanding_only']['isActive']) && $tableFilters['outstanding_only']['isActive']) {
                $query->where('remaining', '>', 0);
            }
            
            // Apply overdue filter
            if (isset($tableFilters['overdue']['isActive']) && $tableFilters['overdue']['isActive']) {
                $query->whereHas('invoice', function (Builder $query) {
                    $query->where('due_date', '<', now());
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
                $query->whereHas('invoice', function (Builder $query) use ($data) {
                    if (isset($data['due_from']) && $data['due_from'] !== null) {
                        $query->whereDate('due_date', '>=', $data['due_from']);
                    }
                    if (isset($data['due_until']) && $data['due_until'] !== null) {
                        $query->whereDate('due_date', '<=', $data['due_until']);
                    }
                });
            }
            
            // Apply overdue days filter
            if (isset($tableFilters['overdue_days']['value']) && !empty($tableFilters['overdue_days']['value'])) {
                $value = $tableFilters['overdue_days']['value'];
                $query->whereHas('invoice', function (Builder $query) use ($value) {
                    $now = now();
                    switch ($value) {
                        case '1-30':
                            $query->whereBetween('due_date', [$now->copy()->subDays(30), $now->copy()->subDay()]);
                            break;
                        case '31-60':
                            $query->whereBetween('due_date', [$now->copy()->subDays(60), $now->copy()->subDays(31)]);
                            break;
                        case '60+':
                            $query->where('due_date', '<', $now->copy()->subDays(60));
                            break;
                    }
                })->where('status', 'Belum Lunas');
            }
        }
        
        return $query;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AccountReceivableStatsWidget::class,
        ];
    }
}
