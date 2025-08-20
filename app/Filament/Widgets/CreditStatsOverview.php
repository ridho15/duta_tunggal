<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use App\Services\CreditValidationService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class CreditStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $creditService = app(CreditValidationService::class);
        
        // Total customers with credit payment type
        $creditCustomers = Customer::where('tipe_pembayaran', 'Kredit')->count();
        
        // Customers with overdue credits
        $overdueCustomers = Customer::where('tipe_pembayaran', 'Kredit')
            ->whereHas('invoices', function ($query) {
                $query->where('due_date', '<', Carbon::now())
                    ->whereIn('status', ['sent', 'partially_paid']);
            })->count();
            
        // Total overdue amount
        $totalOverdueAmount = Invoice::where('due_date', '<', Carbon::now())
            ->whereIn('status', ['sent', 'partially_paid'])
            ->sum('total');
            
        // Customers near credit limit (>= 90%)
        $nearLimitCustomers = Customer::where('tipe_pembayaran', 'Kredit')
            ->where('kredit_limit', '>', 0)
            ->get()
            ->filter(function ($customer) use ($creditService) {
                return $creditService->getCreditUsagePercentage($customer) >= 90;
            })
            ->count();
            
        // Total outstanding receivables
        $totalOutstanding = AccountReceivable::where('status', 'Belum Lunas')
            ->sum('remaining');

        return [
            Stat::make('Customer Kredit', $creditCustomers)
                ->description('Total customer dengan tipe pembayaran kredit')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
                
            Stat::make('Kredit Jatuh Tempo', $overdueCustomers)
                ->description('Customer dengan tagihan jatuh tempo')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
                
            Stat::make('Total Jatuh Tempo', 'Rp ' . number_format($totalOverdueAmount, 0, ',', '.'))
                ->description('Total nilai tagihan yang jatuh tempo')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger'),
                
            Stat::make('Mendekati Limit', $nearLimitCustomers)
                ->description('Customer dengan penggunaan kredit ≥ 90%')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning'),
                
            Stat::make('Total Piutang', 'Rp ' . number_format($totalOutstanding, 0, ',', '.'))
                ->description('Total outstanding receivables')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),
        ];
    }
}