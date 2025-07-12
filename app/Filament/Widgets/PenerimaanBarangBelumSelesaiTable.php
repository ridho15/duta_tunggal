<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseReceipt;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PenerimaanBarangBelumSelesaiTable extends BaseWidget
{
    protected static ?string $heading = "Penerimaan Barang Belum Selesai";

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return PurchaseReceipt::query();
            })
            ->columns([
                // ...
            ]);
    }
}
