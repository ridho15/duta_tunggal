<?php

namespace App\Filament\Widgets;

use App\Models\Quotation;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PenawaranHargaTable extends BaseWidget
{
    protected static ?string $heading = "Penawaran Harga";

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return Quotation::query()
                    ->whereIn('status', ['draft', 'request_approve']);
            })
            ->columns([
                TextColumn::make('quotation_number')
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->date()
                    ->sortable(),
            ]);
    }
}
