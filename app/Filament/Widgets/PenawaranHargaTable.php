<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\QuotationResource;
use App\Models\Quotation;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Enums\ActionsPosition;

class PenawaranHargaTable extends BaseWidget
{
    protected static ?string $heading = "Penawaran Harga Belum Jadi Transaksi";

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return Quotation::query()
                    ->whereIn('status', ['draft', 'request_approve']);
            })->actions([
                ViewAction::make()
                    ->color('primary')
                    ->url(function ($record) {
                        return QuotationResource::getUrl('view', ['record' => $record->id]);
                    })
            ], position: ActionsPosition::BeforeColumns)
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
