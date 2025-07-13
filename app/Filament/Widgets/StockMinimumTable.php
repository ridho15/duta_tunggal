<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\InventoryStockResource;
use App\Models\InventoryStock;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class StockMinimumTable extends BaseWidget
{
    protected static ?string $heading = 'Stock Minimum';
    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return InventoryStock::with(['product', 'warehouse', 'rak'])
                    ->whereColumn('qty_available', '<=', 'qty_min');
            })
            ->actions([
                ViewAction::make()
                    ->color('primary')
                    ->url(function ($record) {
                        return InventoryStockResource::getUrl('view', ['record' => $record->id]);
                    })
            ])
            ->columns([
                TextColumn::make('product')->formatStateUsing(function ($state) {
                    return "({$state->sku}) {$state->name}";
                })->label('Produk'),
                TextColumn::make('warehouse')->formatStateUsing(function ($state) {
                    return "({$state->kode}) {$state->name}";
                })->label('Gudang'),
                TextColumn::make('qty_available')->label('Stok Tersedia')->numeric()->color(function ($record) {
                    return $record->qty_available < $record->qty_min ? 'danger' : null;
                }),
                TextColumn::make('qty_min')->label('Stok Minimum')->numeric(),
                TextColumn::make('rak')->formatStateUsing(function ($state) {
                    return "({$state->code}) {$state->name}";
                })->label('Rak')->toggleable(),
            ]);
    }
}
