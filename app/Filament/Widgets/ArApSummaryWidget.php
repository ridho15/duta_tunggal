<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PurchaseInvoiceResource;
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
        $unpaid = PaymentStatus::UNPAID->value;

        // Account Receivables Summary
        $arStats = AccountReceivable::selectRaw('
            SUM(total) as total_ar,
            SUM(paid) as paid_ar,
            SUM(remaining) as outstanding_ar,
            COUNT(*) as count_ar,
            COUNT(CASE WHEN status = "' . $unpaid . '" THEN 1 END) as unpaid_count_ar
        ')->first();

        // Account Payables Summary. Purchase AP amounts are stored in invoice source
        // currency, so global dashboard totals must be converted to IDR first.
        $accountPayables = AccountPayable::with('invoice')->get();
        $apTotal = $accountPayables->sum(fn ($ap) => PurchaseInvoiceResource::invoiceAmountToIdr($ap->invoice, $ap->total));
        $apOutstanding = $accountPayables->sum(fn ($ap) => PurchaseInvoiceResource::invoiceAmountToIdr($ap->invoice, $ap->remaining));
        $apCount = $accountPayables->count();
        $apUnpaidCount = $accountPayables->where('status', $unpaid)->count();

        // Overdue calculations
        $overdueAR = AccountReceivable::whereHas('invoice', function ($query) {
            $query->where('due_date', '<', now());
        })->where('status', PaymentStatus::UNPAID->value)->sum('remaining');

        $overdueAP = AccountPayable::with('invoice')->whereHas('invoice', function ($query) {
            $query->where('due_date', '<', now());
        })->where('status', PaymentStatus::UNPAID->value)
            ->get()
            ->sum(fn ($ap) => PurchaseInvoiceResource::invoiceAmountToIdr($ap->invoice, $ap->remaining));

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
                
            Card::make('Total Account Payable', MoneyHelper::rupiah($apTotal))
                ->description($apCount . ' invoices, ' . $apUnpaidCount . ' unpaid, converted to IDR')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('info')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(route('filament.admin.resources.account-payables.index')),
                
            Card::make('Outstanding AP', MoneyHelper::rupiah($apOutstanding))
                ->description('Remaining to pay, converted to IDR')
                ->descriptionIcon('heroicon-m-clock')
                ->color($apOutstanding > 0 ? 'warning' : 'success')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(route('filament.admin.resources.account-payables.index', ['tableFilters[outstanding_only][isActive]' => true])),
                
            Card::make('Overdue AP', MoneyHelper::rupiah($overdueAP))
                ->description('Past due amount, converted to IDR')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueAP > 0 ? 'danger' : 'success')
                ->extraAttributes(['class' => 'cursor-pointer'])
                ->url(route('filament.admin.resources.account-payables.index', ['tableFilters[overdue][isActive]' => true])),
        ];
    }
}
