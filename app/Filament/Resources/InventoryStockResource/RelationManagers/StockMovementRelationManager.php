<?php

namespace App\Filament\Resources\InventoryStockResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockMovementRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    protected static ?string $title = 'Riwayat Pergerakan Stok';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->color(function ($state) {
                        return match ($state) {
                            'purchase_in' => 'success',
                            'sales' => 'danger',
                            'transfer_in' => 'primary',
                            'transfer_out' => 'warning',
                            'manufacture_in' => 'info',
                            'manufacture_out' => 'warning',
                            'adjustment_in' => 'secondary',
                            'adjustment_out' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'purchase_in' => 'Purchase In',
                            'sales' => 'Sales',
                            'transfer_in' => 'Transfer In',
                            'transfer_out' => 'Transfer Out',
                            'manufacture_in' => 'Manufacture In',
                            'manufacture_out' => 'Manufacture Out',
                            'adjustment_in' => 'Adjustment In',
                            'adjustment_out' => 'Adjustment Out',
                            default => '-',
                        };
                    })
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('value')
                    ->label('Nilai')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->searchable(),
                TextColumn::make('rak.name')
                    ->label('Rak')
                    ->searchable()
                    ->default('-'),
                TextColumn::make('fromModel')
                    ->label('Sumber')
                    ->formatStateUsing(function ($record) {
                        if ($record->fromModel) {
                            $modelType = $record->from_model_type;
                            $modelName = match ($modelType) {
                                'App\Models\SaleOrder' => 'Sales Order',
                                'App\Models\PurchaseOrder' => 'Purchase Order',
                                'App\Models\DeliveryOrder' => 'Delivery Order',
                                'App\Models\PurchaseReceipt' => 'Purchase Receipt',
                                'App\Models\StockTransfer' => 'Stock Transfer',
                                'App\Models\ManufacturingOrder' => 'Manufacturing Order',
                                'App\Models\StockAdjustment' => 'Stock Adjustment',
                                'App\Models\QualityControl' => 'Quality Control',
                                'App\Models\PurchaseReturn' => 'Purchase Return',
                                default => 'Unknown',
                            };

                            $sourceNumber = match ($modelType) {
                                'App\Models\SaleOrder' => $record->fromModel->so_number ?? 'N/A',
                                'App\Models\PurchaseOrder' => $record->fromModel->po_number ?? 'N/A',
                                'App\Models\DeliveryOrder' => $record->fromModel->do_number ?? 'N/A',
                                'App\Models\PurchaseReceipt' => $record->fromModel->receipt_number ?? 'N/A',
                                'App\Models\StockTransfer' => $record->fromModel->transfer_number ?? 'N/A',
                                'App\Models\ManufacturingOrder' => $record->fromModel->mo_number ?? 'N/A',
                                'App\Models\StockAdjustment' => $record->fromModel->adjustment_number ?? 'N/A',
                                'App\Models\QualityControl' => $record->fromModel->qc_number ?? 'N/A',
                                'App\Models\PurchaseReturn' => $record->fromModel->nota_retur ?? 'N/A',
                                default => 'N/A',
                            };

                            return $modelName . ': ' . $sourceNumber;
                        }

                        return '-';
                    }),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
