<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PoBelumSelesaiTable extends BaseWidget
{
    protected static ?string $heading = "PO Belum Selesai";
    public function table(Table $table): Table
    {
        return $table
            ->query(function(){
                return PurchaseOrder::query();
            })
            ->columns([
                // ...
            ]);
    }
}
