<?php

namespace App\Filament\Resources\Reports\InventoryCardResource\Pages;

use App\Filament\Resources\Reports\InventoryCardResource;
use App\Models\Product;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ViewInventoryCard extends Page
{
    protected static string $resource = InventoryCardResource::class;

    protected static string $view = 'filament.pages.reports.inventory-card';

    // Filter state
    public ?string $startDate   = null;
    public ?string $endDate     = null;
    public ?int    $productId   = null;
    public ?int    $warehouseId = null;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate   = now()->endOfMonth()->format('Y-m-d');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview Laporan')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->action(function () {
                    if (!$this->startDate || !$this->endDate) {
                        Notification::make()
                            ->title('Tanggal wajib diisi')
                            ->danger()
                            ->send();
                        return;
                    }
                    $url = $this->getPreviewUrl();
                    $this->dispatch('open-inventory-card-preview', url: $url);

                    Notification::make()
                        ->title('Laporan Kartu Persediaan')
                        ->body('Laporan sedang dibuka. Jika popup terblokir oleh browser, klik tombol di bawah:')
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('open')
                                ->label('Buka Laporan')
                                ->button()
                                ->url($url, shouldOpenInNewTab: true),
                        ])
                        ->success()
                        ->send();
                }),

            Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn () => route('inventory-card.pdf', $this->buildQueryParams()), shouldOpenInNewTab: true),

            Action::make('download_excel')
                ->label('Download Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(fn () => route('inventory-card.excel', $this->buildQueryParams()), shouldOpenInNewTab: true),
        ];
    }

    public function buildQueryParams(): array
    {
        return array_filter([
            'start'        => $this->startDate,
            'end'          => $this->endDate,
            'product_id'   => $this->productId,
            'warehouse_id' => $this->warehouseId,
        ]);
    }

    public function getPreviewUrl(): string
    {
        return route('inventory-card.print', $this->buildQueryParams());
    }

    public function getPrintUrl(): string
    {
        return $this->getPreviewUrl();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Filter Kartu Persediaan')
                ->columns(2)
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Tanggal Mulai')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->live()
                        ->default(now()->startOfMonth()),

                    DatePicker::make('endDate')
                        ->label('Tanggal Akhir')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->live()
                        ->default(now()->endOfMonth()),

                    Select::make('productId')
                        ->label('Item (Produk)')
                        ->options(fn () => Product::query()->orderBy('name')->limit(100)->get()->mapWithKeys(fn ($product) => [
                            $product->id => ($product->sku ? '[' . $product->sku . '] ' : '') . $product->name
                        ]))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->placeholder('— Semua Produk —')
                        ->columnSpanFull(),

                    Select::make('warehouseId')
                        ->label('Gudang')
                        ->options(fn () => Warehouse::query()->orderBy('name')->get()->mapWithKeys(fn ($warehouse) => [
                            $warehouse->id => $warehouse->name . ($warehouse->kode ? ' (' . $warehouse->kode . ')' : '')
                        ]))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->placeholder('— Semua Gudang —')
                        ->columnSpanFull(),
                ]),
        ];
    }
}
