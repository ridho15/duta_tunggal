<?php

namespace App\Filament\Widgets;

use App\Models\InventoryStock;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class StockMinimumTable extends BaseWidget
{
    protected static ?string $heading = 'Stock Minimum';
    public function table(Table $table): Table
    {
        return $table
            ->query(function(){
                return InventoryStock::query();
            })
            ->columns([
                // ...
            ]);
    }
}
