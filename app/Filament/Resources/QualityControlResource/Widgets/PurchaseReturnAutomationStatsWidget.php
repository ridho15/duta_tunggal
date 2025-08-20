<?php

namespace App\Filament\Resources\QualityControlResource\Widgets;

use App\Services\PurchaseReturnAutomationService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PurchaseReturnAutomationStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $automationService = app(PurchaseReturnAutomationService::class);
        $stats = $automationService->getAutomationStats();

        return [
            Stat::make('Pending QC Rejects', $stats['pending_qc_rejects'])
                ->description('Quality control items with rejected quantities waiting for return processing')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'cursor-pointer'
                ]),
                
            Stat::make('Processed This Month', $stats['processed_this_month'])
                ->description('Quality control items processed for returns this month')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            Stat::make('Automated Returns', $stats['total_automated_returns'])
                ->description('Purchase returns created automatically this month')
                ->descriptionIcon('heroicon-m-arrow-up-on-square-stack')
                ->color('info'),
        ];
    }
}
