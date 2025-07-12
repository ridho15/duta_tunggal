<?php

namespace App\Filament\Widgets;

use App\Models\SaleOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SoBelumSelesaiTable extends BaseWidget
{
    protected static ?string $heading = 'SO Belum Selesai';
    public function table(Table $table): Table
    {
        return $table
            ->query(function(){
                return SaleOrder::query();
            })
            ->columns([
                // ...
            ]);
    }
}
