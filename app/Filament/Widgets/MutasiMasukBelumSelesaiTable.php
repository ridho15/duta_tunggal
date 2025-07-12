<?php

namespace App\Filament\Widgets;

use App\Models\StockTransfer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MutasiMasukBelumSelesaiTable extends BaseWidget
{
    protected static ?string $heading = "Mutasi Masuk Belum Selesai";
    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return StockTransfer::query();
            })
            ->columns([
                // ...
            ]);
    }
}
