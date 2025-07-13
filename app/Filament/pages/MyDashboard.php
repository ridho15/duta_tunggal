<?php

namespace App\Filament;

use App\Filament\Widgets\CalendarWidget;
use App\Filament\Widgets\Penjualan7HariChart;
use App\Filament\Widgets\PenjualanOverview;
use App\Filament\Widgets\PenjualanPerKategoriChart;
use App\Filament\Widgets\ProdukTerlarisChart;
use App\Filament\Widgets\StockMinimumTable;
use App\Filament\Widgets\TopCustomerChart;
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
        if (Auth::user()->hasRole(['Owner', 'Super Admin'])) {
            return [
                AccountWidget::class,
                // CalendarWidget::class,
                PenjualanOverview::class,
                Penjualan7HariChart::class,
                ProdukTerlarisChart::class,
                StockMinimumTable::class,
                PenjualanPerKategoriChart::class,
                TopCustomerChart::class,
            ];
        }

        return [];
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
