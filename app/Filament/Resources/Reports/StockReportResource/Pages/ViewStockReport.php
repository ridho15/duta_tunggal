<?php

namespace App\Filament\Resources\Reports\StockReportResource\Pages;

use App\Filament\Resources\Reports\StockReportResource;
use App\Models\Product;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ViewStockReport extends Page
{
    protected static string $resource = StockReportResource::class;

    protected static string $view = 'filament.pages.reports.stock-report';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public array $productIds = [];

    public array $warehouseIds = [];

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate   = now()->format('Y-m-d');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview Laporan')
                ->icon('heroicon-o-eye')
                ->color('success')
                ->action(function () {
                    if (!$this->startDate || !$this->endDate) {
                        Notification::make()
                            ->title('Tanggal wajib diisi')
                            ->danger()
                            ->send();
                        return;
                    }
                    $this->dispatch('open-stock-preview', url: $this->getPreviewUrl());
                }),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Filter Laporan Stok')
                ->columns(2)
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Tanggal Mulai')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->default(now()->startOfMonth()),

                    DatePicker::make('endDate')
                        ->label('Tanggal Selesai')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->default(now()),

                    Select::make('productIds')
                        ->label('Item / Produk')
                        ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull()
                        ->helperText('Kosongkan untuk menampilkan semua produk'),

                    Select::make('warehouseIds')
                        ->label('Gudang')
                        ->options(fn () => Warehouse::query()->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull()
                        ->helperText('Kosongkan untuk menampilkan semua gudang'),
                ]),
        ];
    }

    /** Build the preview URL with current filter values as query params. */
    public function getPreviewUrl(): string
    {
        $params = [
            'start_date'    => $this->startDate,
            'end_date'      => $this->endDate,
            'product_ids'   => $this->productIds,
            'warehouse_ids' => $this->warehouseIds,
        ];

        return route('reports.stock-report.preview') . '?' . http_build_query($params);
    }
}
