<?php

namespace App\Filament\Widgets;

use App\Helpers\MoneyHelper;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Support\Facades\DB;

class ArApSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getStats(): array
    {
        // Account Receivables Summary
        $arStats = AccountReceivable::selectRaw('
            SUM(total) as total_ar,
            SUM(paid) as paid_ar,
            SUM(remaining) as outstanding_ar,
            COUNT(*) as count_ar,
            COUNT(CASE WHEN status = "Belum Lunas" THEN 1 END) as unpaid_count_ar
        ')->first();

        // Account Payables Summary  
        $apStats = AccountPayable::selectRaw('
            SUM(total) as total_ap,
            SUM(paid) as paid_ap,
            SUM(remaining) as outstanding_ap,
            COUNT(*) as count_ap,
            COUNT(CASE WHEN status = "Belum Lunas" THEN 1 END) as unpaid_count_ap
        ')->first();

        // Overdue calculations
        $overdueAR = AccountReceivable::whereHas('invoice', function ($query) {
            $query->where('due_date', '<', now());
        })->where('status', 'Belum Lunas')->sum('remaining');

        $overdueAP = AccountPayable::whereHas('invoice', function ($query) {
            $query->where('due_date', '<', now());
        })->where('status', 'Belum Lunas')->sum('remaining');

        return [
            Card::make('Total Account Receivable', MoneyHelper::rupiah($arStats->total_ar ?? 0))
                ->description($arStats->count_ar . ' invoices, ' . $arStats->unpaid_count_ar . ' unpaid')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(route('filament.admin.resources.account-receivables.index')),
                
            Card::make('Outstanding AR', MoneyHelper::rupiah($arStats->outstanding_ar ?? 0))
                ->description('Remaining to collect')
                ->descriptionIcon('heroicon-m-clock')
                ->color($arStats->outstanding_ar > 0 ? 'warning' : 'success')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(route('filament.admin.resources.account-receivables.index', ['tableFilters[outstanding_only][isActive]' => true])),
                
            Card::make('Overdue AR', MoneyHelper::rupiah($overdueAR))
                ->description('Past due amount')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueAR > 0 ? 'danger' : 'success')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(route('filament.admin.resources.account-receivables.index', ['tableFilters[overdue][isActive]' => true])),
                
            Card::make('Total Account Payable', MoneyHelper::rupiah($apStats->total_ap ?? 0))
                ->description($apStats->count_ap . ' invoices, ' . $apStats->unpaid_count_ap . ' unpaid')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('info')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(route('filament.admin.resources.account-payables.index')),
                
            Card::make('Outstanding AP', MoneyHelper::rupiah($apStats->outstanding_ap ?? 0))
                ->description('Remaining to pay')
                ->descriptionIcon('heroicon-m-clock')
                ->color($apStats->outstanding_ap > 0 ? 'warning' : 'success')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(route('filament.admin.resources.account-payables.index', ['tableFilters[outstanding_only][isActive]' => true])),
                
            Card::make('Overdue AP', MoneyHelper::rupiah($overdueAP))
                ->description('Past due amount')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueAP > 0 ? 'danger' : 'success')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(route('filament.admin.resources.account-payables.index', ['tableFilters[overdue][isActive]' => true])),
        ];
    }
}
