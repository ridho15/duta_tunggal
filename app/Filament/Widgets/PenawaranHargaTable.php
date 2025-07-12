<?php

namespace App\Filament\Widgets;

use App\Models\Quotation;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PenawaranHargaTable extends BaseWidget
{
    protected static ?string $heading = "Penawaran Harga";

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return Quotation::query();
            })
            ->columns([
                // ...
            ]);
    }
}
