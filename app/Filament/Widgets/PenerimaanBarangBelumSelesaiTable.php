<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PurchaseReceiptResource;
use App\Models\PurchaseReceipt;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PenerimaanBarangBelumSelesaiTable extends BaseWidget
{
    protected static ?string $heading = "Penerimaan Barang Belum Selesai";

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return PurchaseReceipt::query()
                    ->where('status', '!=', 'completed');
            })
            ->actions([
                ViewAction::make()
                    ->color('primary')
                    ->url(function ($record) {
                        return PurchaseReceiptResource::getUrl('view', ['record' => $record]);
                    })
            ])
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt Number')
                    ->searchable(),
                TextColumn::make('purchaseOrder.po_number')
                    ->label('PO Number')
                    ->searchable(),
                TextColumn::make('receipt_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('receivedBy.name')
                    ->label('Received By')
                    ->searchable(),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->searchable(),
                TextColumn::make('currency.name')
                    ->label('Currency'),
                TextColumn::make('other_cost')
                    ->money('idr')
                    ->sortable(),
                SelectColumn::make('status')
                    ->options(function () {
                        return [
                            'draft' => 'Draft',
                            'partial' => 'Partial',
                            'completed' => 'Completed'
                        ];
                    }),
            ]);
    }
}
