<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovementResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Stock Movement Details')
                    ->schema([
                        TextEntry::make('product.name')
                            ->label('Product')
                            ->formatStateUsing(function ($record) {
                                return "({$record->product->sku}) {$record->product->name}";
                            }),
                        TextEntry::make('warehouse.name')
                            ->label('Warehouse')
                            ->formatStateUsing(function ($record) {
                                return "({$record->warehouse->kode}) {$record->warehouse->name}";
                            }),
                        TextEntry::make('rak.name')
                            ->label('Rak')
                            ->formatStateUsing(function ($record) {
                                return "({$record->rak->code}) {$record->rak->name}";
                            }),
                        TextEntry::make('quantity')
                            ->label('Quantity'),
                        TextEntry::make('type')
                            ->label('Type')
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
                                    default => '-'
                                };
                            }),
                        TextEntry::make('reference_id')
                            ->label('Reference ID'),
                        TextEntry::make('date')
                            ->label('Date')
                            ->dateTime(),
                        TextEntry::make('notes')
                            ->label('Notes'),
                    ])->columns(2),
                Section::make('Source Information')
                    ->schema([
                        TextEntry::make('from_model_type')
                            ->label('Source Type')
                            ->formatStateUsing(function ($state) {
                                return match ($state) {
                                    'App\Models\SaleOrder' => 'Sales Order',
                                    'App\Models\PurchaseOrder' => 'Purchase Order',
                                    'App\Models\DeliveryOrder' => 'Delivery Order',
                                    'App\Models\PurchaseReceipt' => 'Purchase Receipt',
                                    'App\Models\StockTransfer' => 'Stock Transfer',
                                    'App\Models\ManufacturingOrder' => 'Manufacturing Order',
                                    'App\Models\StockAdjustment' => 'Stock Adjustment',
                                    default => 'Unknown'
                                };
                            }),
                        TextEntry::make('fromModel')
                            ->label('Source Number')
                            ->formatStateUsing(function ($record) {
                                if ($record->fromModel) {
                                    $modelType = $record->from_model_type;
                                    return match ($modelType) {
                                        'App\Models\SaleOrder' => $record->fromModel->so_number ?? 'N/A',
                                        'App\Models\PurchaseOrder' => $record->fromModel->po_number ?? 'N/A',
                                        'App\Models\DeliveryOrder' => $record->fromModel->do_number ?? 'N/A',
                                        'App\Models\PurchaseReceipt' => $record->fromModel->receipt_number ?? 'N/A',
                                        'App\Models\StockTransfer' => $record->fromModel->transfer_number ?? 'N/A',
                                        'App\Models\ManufacturingOrder' => $record->fromModel->mo_number ?? 'N/A',
                                        'App\Models\StockAdjustment' => $record->fromModel->adjustment_number ?? 'N/A',
                                        default => 'N/A'
                                    };
                                }
                                return 'N/A';
                            }),
                        TextEntry::make('fromModel')
                            ->label('Source Link')
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
                                        default => 'Unknown'
                                    };

                                    $sourceNumber = match ($modelType) {
                                        'App\Models\SaleOrder' => $record->fromModel->so_number ?? 'N/A',
                                        'App\Models\PurchaseOrder' => $record->fromModel->po_number ?? 'N/A',
                                        'App\Models\DeliveryOrder' => $record->fromModel->do_number ?? 'N/A',
                                        'App\Models\PurchaseReceipt' => $record->fromModel->receipt_number ?? 'N/A',
                                        'App\Models\StockTransfer' => $record->fromModel->transfer_number ?? 'N/A',
                                        'App\Models\ManufacturingOrder' => $record->fromModel->mo_number ?? 'N/A',
                                        'App\Models\StockAdjustment' => $record->fromModel->adjustment_number ?? 'N/A',
                                        default => 'N/A'
                                    };

                                    return $modelName . ' - ' . $sourceNumber;
                                }
                                return 'No Source';
                            }),
                    ])->columns(3),
            ]);
    }
}
