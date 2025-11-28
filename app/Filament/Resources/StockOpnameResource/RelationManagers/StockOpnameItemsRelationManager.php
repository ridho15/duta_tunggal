<?php

namespace App\Filament\Resources\StockOpnameResource\RelationManagers;

use App\Models\Product;
use App\Models\Rak;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StockOpnameItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('product_id')
                    ->label('Product')
                    ->options(Product::pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $product = Product::find($state);
                            // Get current stock from inventory_stocks
                            $warehouseId = $this->getOwnerRecord()->warehouse_id;
                            $inventoryStock = \App\Models\InventoryStock::where('product_id', $state)
                                ->where('warehouse_id', $warehouseId)
                                ->first();

                            if ($inventoryStock) {
                                $set('system_qty', $inventoryStock->qty_available);
                            } else {
                                $set('system_qty', 0);
                            }

                            // Calculate average cost from purchase history
                            $opnameDate = $this->getOwnerRecord()->opname_date ?? now();
                            $averageCost = $this->calculateAverageCostForProduct($state, $opnameDate);
                            $set('average_cost', $averageCost);
                            $set('unit_cost', $averageCost); // Set unit cost to average cost by default
                        }
                    }),

                Select::make('rak_id')
                    ->label('Rak')
                    ->options(Rak::pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                TextInput::make('system_qty')
                    ->label('Qty Sistem')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('physical_qty')
                    ->label('Qty Fisik (Opname)')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                        $systemQty = $get('system_qty') ?? 0;
                        $physicalQty = $state ?? 0;
                        $difference = $physicalQty - $systemQty;
                        $set('difference_qty', $difference);
                    }),

                TextInput::make('difference_qty')
                    ->label('Selisih Qty')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('unit_cost')
                    ->label('Harga Satuan')
                    ->numeric()
                    ->default(0)
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                        $differenceQty = $get('difference_qty') ?? 0;
                        $unitCost = $state ?? 0;
                        $differenceValue = $differenceQty * $unitCost;
                        $set('difference_value', $differenceValue);

                        // Update total value
                        $physicalQty = $get('physical_qty') ?? 0;
                        $totalValue = $physicalQty * $unitCost;
                        $set('total_value', $totalValue);
                    }),

                TextInput::make('average_cost')
                    ->label('Average Cost')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Harga rata-rata berdasarkan riwayat pembelian'),

                TextInput::make('difference_value')
                    ->label('Nilai Selisih')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('total_value')
                    ->label('Total Nilai')
                    ->numeric()
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Total nilai berdasarkan qty fisik × harga satuan'),

                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('rak.name')
                    ->label('Rak')
                    ->searchable(),

                TextColumn::make('system_qty')
                    ->label('Qty Sistem')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('physical_qty')
                    ->label('Qty Fisik')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('difference_qty')
                    ->label('Selisih Qty')
                    ->numeric()
                    ->color(fn ($record) => $record->difference_qty > 0 ? 'success' : ($record->difference_qty < 0 ? 'danger' : 'gray'))
                    ->sortable(),

                TextColumn::make('unit_cost')
                    ->label('Harga Satuan')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('average_cost')
                    ->label('Average Cost')
                    ->money('IDR')
                    ->sortable()
                    ->color('info'),

                TextColumn::make('difference_value')
                    ->label('Nilai Selisih')
                    ->money('IDR')
                    ->color(fn ($record) => $record->difference_value > 0 ? 'success' : ($record->difference_value < 0 ? 'danger' : 'gray'))
                    ->sortable(),

                TextColumn::make('total_value')
                    ->label('Total Nilai')
                    ->money('IDR')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Calculate average cost for a product based on purchase history
     */
    private function calculateAverageCostForProduct($productId, $opnameDate)
    {
        // Get all purchase receipts for this product before the opname date
        $purchaseItems = \App\Models\PurchaseReceiptItem::where('product_id', $productId)
            ->whereHas('purchaseReceipt', function($query) use ($opnameDate) {
                $query->where('receipt_date', '<=', $opnameDate);
            })
            ->with('purchaseReceipt')
            ->orderBy('purchase_receipt_items.created_at', 'asc')
            ->get();

        if ($purchaseItems->isEmpty()) {
            // If no purchase history, return 0 or get from product cost
            $product = Product::find($productId);
            return $product ? ($product->cost ?? 0) : 0;
        }

        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($purchaseItems as $item) {
            $quantity = $item->quantity_received ?? $item->quantity ?? 0;
            $unitPrice = $item->unit_price ?? 0;

            $totalQuantity += $quantity;
            $totalValue += ($quantity * $unitPrice);
        }

        return $totalQuantity > 0 ? $totalValue / $totalQuantity : 0;
    }
}