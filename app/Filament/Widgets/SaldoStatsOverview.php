<?php

namespace App\Filament\Widgets;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SaldoStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $kasCoaId = ChartOfAccount::where('code', '1-1001')->value('id');
        $bankCoaId = ChartOfAccount::where('code', '1-1002')->value('id');

        // Hitung saldo dari jurnal (debit - kredit) berdasarkan coa_id
        $saldoKas = JournalEntry::where('coa_id', $kasCoaId)
            ->sum(DB::raw('debit - credit'));

        $saldoBank = JournalEntry::where('coa_id', $bankCoaId)
            ->sum(DB::raw('debit - credit'));
        return [
            Stat::make('Saldo Kas', "Rp." . number_format($saldoKas, 0, ',', '.'))
                ->description('Total Kas')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Saldo Bank', "Rp." . number_format($saldoBank, 0, ',', '.'))
                ->description('Total Saldo Bank')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }
}
