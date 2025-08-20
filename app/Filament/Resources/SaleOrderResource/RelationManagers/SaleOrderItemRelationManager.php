<?php

namespace App\Filament\Resources\SaleOrderResource\RelationManagers;

use App\Http\Controllers\HelperController;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SaleOrderItemRelationManager extends RelationManager
{
    protected static string $relationship = 'saleOrderItem';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Sales Order Item')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $product = Product::find($state);
                                $set('unit_price', $product->sell_price);
                                $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax')));
                            })
                            ->helperText(function ($get) {
                                if (!$get('product_id')) return null;
                                
                                $inventoryStock = \App\Models\InventoryStock::where('product_id', $get('product_id'))
                                    ->sum('qty_available');
                                
                                return "Stock tersedia: " . number_format($inventoryStock, 0, ',', '.');
                            })
                            ->required()
                            ->relationship('product', 'id')
                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                return "({$product->sku}) {$product->name}";
                            }),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $state, $get('tax')));
                            })
                            ->helperText(function ($get) {
                                if (!$get('product_id') || !$get('quantity')) return null;
                                
                                $inventoryStock = \App\Models\InventoryStock::where('product_id', $get('product_id'))
                                    ->sum('qty_available');
                                
                                $quantity = (float) $get('quantity');
                                
                                if ($inventoryStock < $quantity) {
                                    return "⚠️ Stock tidak mencukupi! Tersedia: " . number_format($inventoryStock, 0, ',', '.');
                                } else {
                                    return "✅ Stock tersedia: " . number_format($inventoryStock, 0, ',', '.');
                                }
                            })
                            ->rule(function ($get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if (!$get('product_id')) return;
                                    
                                    $inventoryStock = \App\Models\InventoryStock::where('product_id', $get('product_id'))
                                        ->sum('qty_available');
                                    
                                    if ($inventoryStock < $value) {
                                        $fail('Quantity melebihi stock yang tersedia (' . number_format($inventoryStock, 0, ',', '.') . ')');
                                    }
                                };
                            })
                            ->required()
                            ->default(0),
                        TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $state));
                            })
                            ->prefix('Rp.'),
                        TextInput::make('discount')
                            ->label('Discount')
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax')));
                            })
                            ->prefix('Rp.'),
                        TextInput::make('tax')
                            ->label('Tax')
                            ->numeric()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax')));
                            })
                            ->default(0)
                            ->prefix('Rp.'),
                        TextInput::make('subtotal')
                            ->label('Sub Total')
                            ->reactive()
                            ->readOnly()
                            ->default(0)
                            ->prefix('Rp.')
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('product')
                    ->label('Product')
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    }),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->badge()
                    ->color(function ($state, $record) {
                        $inventoryStock = \App\Models\InventoryStock::where('product_id', $record->product_id)
                            ->where(function ($query) use ($record) {
                                $query->where('warehouse_id', $record->warehouse_id)
                                      ->orWhere('rak_id', $record->rak_id);
                            })
                            ->first();
                        
                        $availableStock = $inventoryStock ? $inventoryStock->qty_available : 0;
                        
                        if ($availableStock < $state) {
                            return 'danger'; // Red if quantity exceeds available stock
                        }
                        return 'primary'; // Blue for normal quantity
                    })
                    ->sortable(),
                TextColumn::make('available_stock')
                    ->label('Stock Tersedia')
                    ->getStateUsing(function ($record) {
                        $inventoryStock = \App\Models\InventoryStock::where('product_id', $record->product_id)
                            ->where(function ($query) use ($record) {
                                $query->where('warehouse_id', $record->warehouse_id)
                                      ->orWhere('rak_id', $record->rak_id);
                            })
                            ->first();
                        
                        return $inventoryStock ? $inventoryStock->qty_available : 0;
                    })
                    ->badge()
                    ->color(function ($state, $record) {
                        if ($state < $record->quantity) {
                            return 'danger'; // Red if insufficient stock
                        } elseif ($state <= ($record->quantity * 1.2)) {
                            return 'warning'; // Yellow if stock is low (within 20% of quantity)
                        }
                        return 'success'; // Green if sufficient stock
                    })
                    ->sortable(false),
                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('discount')
                    ->label('Discount')
                    ->suffix(' %')
                    ->sortable(),
                TextColumn::make('tax')
                    ->label('Tax')
                    ->suffix(' %')
                    ->sortable(),
                TextColumn::make('id')
                    ->label('Sub Total')
                    ->money('idr')
                    ->formatStateUsing(function ($record) {
                        $hasil = HelperController::hitungSubtotal($record->quantity, $record->unit_price, $record->discount, $record->tax);
                        return "Rp. " . number_format($hasil, 2, ',', '.');
                    })
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // CreateAction::make()
                //     ->icon('heroicon-o-plus-circle'),
            ])
            ->actions([
            ])
            ->bulkActions([
                
            ]);
    }
}
