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
                    $this->dispatch('open-inventory-card-preview', url: $this->getPreviewUrl());
                }),
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
                        ->default(now()->startOfMonth()),

                    DatePicker::make('endDate')
                        ->label('Tanggal Akhir')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->default(now()->endOfMonth()),

                    Select::make('productId')
                        ->label('Item (Produk)')
                        ->options(fn () => Product::query()->orderBy('name')->get()->mapWithKeys(fn ($product) => [
                            $product->id => $product->name . ($product->sku ? ' (' . $product->sku . ')' : '')
                        ]))
                        ->searchable()
                        ->preload()
                        ->placeholder('— Semua Produk —')
                        ->columnSpanFull(),

                    Select::make('warehouseId')
                        ->label('Gudang')
                        ->options(fn () => Warehouse::query()->orderBy('name')->get()->mapWithKeys(fn ($warehouse) => [
                            $warehouse->id => $warehouse->name . ($warehouse->kode ? ' (' . $warehouse->kode . ')' : '')
                        ]))
                        ->searchable()
                        ->preload()
                        ->placeholder('— Semua Gudang —')
                        ->columnSpanFull(),
                ]),
        ];
    }
}
