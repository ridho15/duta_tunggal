<?php

namespace App\Filament\Resources\InventoryStockResource\Pages;

use App\Filament\Resources\InventoryStockResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryStock extends ViewRecord
{
    protected static string $resource = InventoryStockResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Produk & Lokasi')
                    ->schema([
                        TextEntry::make('product.sku')
                            ->label('SKU'),
                        TextEntry::make('product.name')
                            ->label('Nama Produk'),
                        TextEntry::make('warehouse')
                            ->label('Gudang')
                            ->formatStateUsing(fn ($state) => "({$state->kode}) {$state->name}"),
                        TextEntry::make('rak')
                            ->label('Rak')
                            ->formatStateUsing(fn ($state) => $state ? "({$state->code}) {$state->name}" : '-'),
                    ])
                    ->columns(2),
                Section::make('Informasi Stok')
                    ->schema([
                        TextEntry::make('qty_available')
                            ->label('Qty Available')
                            ->numeric(),
                        TextEntry::make('qty_reserved')
                            ->label('Qty Reserved')
                            ->numeric(),
                        TextEntry::make('qty_on_hand')
                            ->label('Qty On Hand (Available - Reserved)')
                            ->numeric(),
                        TextEntry::make('qty_min')
                            ->label('Qty Minimal')
                            ->numeric(),
                    ])
                    ->columns(2),
                Section::make('Waktu')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Diperbarui')
                            ->dateTime('d M Y H:i'),
                    ])
                    ->columns(2),
            ]);
    }
}

