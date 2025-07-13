<?php

namespace App\Filament\Widgets;

use App\Models\StockTransfer;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MutasiKeluarBelumSelesaiTable extends BaseWidget
{
    protected static ?string $heading = "Mutasi Keluar Belum Selesai";
    public function table(Table $table): Table
    {
        return $table
            ->query(function(){
                return StockTransfer::query();
            })
            ->columns([
                // ...
            ]);
    }
}
