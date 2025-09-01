<?php

namespace App\Filament\pages;

use App\Filament\Widgets\AccontReceivablePayableChart;
use App\Filament\Widgets\AccountReceivablePayableChart;
use App\Filament\Widgets\AgeingScheduleChart;
use App\Filament\Widgets\ArApChart;
use App\Filament\Widgets\ArApSummaryWidget;
use App\Filament\Widgets\CalendarWidget;
use App\Filament\Widgets\DoBelumSelesaiTable;
use App\Filament\Widgets\MutasiKeluarBelumSelesaiTable;
use App\Filament\Widgets\MutasiMasukBelumSelesaiTable;
use App\Filament\Widgets\PenawaranHargaTable;
use App\Filament\Widgets\PenerimaanBarangBelumSelesaiTable;
use App\Filament\Widgets\Penjualan7HariChart;
use App\Filament\Widgets\PenjualanOverview;
use App\Filament\Widgets\PenjualanPerKategoriChart;
use App\Filament\Widgets\PoBelumSelesaiTable;
use App\Filament\Widgets\ProdukTerlarisChart;
use App\Filament\Widgets\SaldoStatsOverview;
use App\Filament\Widgets\SoBelumSelesaiTable;
use App\Filament\Widgets\StockMinimumTable;
use App\Filament\Widgets\TopCustomerChart;
use App\Filament\Widgets\TopTagihanOutstanding;
use App\Filament\Widgets\TotalPenjualanPembelianStats;
use App\Filament\Widgets\UmurHutangChart;
use App\Filament\Widgets\UmurPiutangChart;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\Auth;

class MyDashboard extends Dashboard
{
    use HasFiltersForm;

    public function getWidgets(): array
    {
        $listWidgets = [];
        $listWidgets[] = AccountWidget::class;
        if (Auth::user()->hasRole(['Owner', 'Super Admin'])) {
            $listWidgets[] = SaldoStatsOverview::class;
            $listWidgets[] = PenjualanOverview::class;
            $listWidgets[] = Penjualan7HariChart::class;
            $listWidgets[] = ProdukTerlarisChart::class;
            $listWidgets[] = StockMinimumTable::class;
            $listWidgets[] = PenjualanPerKategoriChart::class;
            $listWidgets[] = AccountReceivablePayableChart::class;
            $listWidgets[] = TopCustomerChart::class;
            $listWidgets[] = TopTagihanOutstanding::class;
        }
        if (Auth::user()->hasRole(['Accounting', 'Super Admin', 'Finance Manager'])) {
            $listWidgets[] = SaldoStatsOverview::class;
            $listWidgets[] = ArApSummaryWidget::class;
            $listWidgets[] = TotalPenjualanPembelianStats::class;
            $listWidgets[] = AgeingScheduleChart::class;
            $listWidgets[] = UmurPiutangChart::class;
            $listWidgets[] = UmurHutangChart::class;
        }
        if (Auth::user()->hasRole(['Sales', 'Super Admin'])) {
            $listWidgets[] = PenawaranHargaTable::class;
            $listWidgets[] = SoBelumSelesaiTable::class;
        }
        if (Auth::user()->hasRole(['Inventory Manager', 'Admin Inventory', 'Purchasing', 'Super Admin'])) {
            $listWidgets[] = StockMinimumTable::class;
            $listWidgets[] = PoBelumSelesaiTable::class;
        }
        if (Auth::user()->hasRole(['Super Admin', 'Inventory Manager', 'Admin Inventory'])) {
            $listWidgets[] = DoBelumSelesaiTable::class;
            $listWidgets[] = MutasiKeluarBelumSelesaiTable::class;
            $listWidgets[] = MutasiMasukBelumSelesaiTable::class;
            $listWidgets[] = PenerimaanBarangBelumSelesaiTable::class;
        }

        $listWidgets = array_unique($listWidgets);

        return $listWidgets;
    }

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('tanggalMulai')
                            ->label('Tanggal Mulai')
                            ->reactive()
                            ->default(function () {
                                return Carbon::now()->subDays(7);
                            }),
                        DatePicker::make('tanggalAkhir')
                            ->label('Tanggal Akhir')
                            ->reactive()
                            ->default(function () {
                                return Carbon::now();
                            }),
                    ])
                    ->columns(2),
            ]);
    }
}