<?php

namespace App\Filament\Resources\SaleOrderResource\RelationManagers;

use App\Http\Controllers\HelperController;
use App\Models\Product;
use App\Models\TaxSetting;
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

    protected static function normalizeTaxTypeValue(?string $taxType): string
    {
        $normalized = strtolower(trim((string) $taxType));

        return match ($normalized) {
            'none', 'non pajak', 'non-pajak', 'nonpajak' => 'none',
            'inklusif', 'included', 'ppn included', 'ppn-included' => 'inklusif',
            'eksklusif', 'eklusif', 'exclusive', 'ppn excluded', 'ppn_excluded' => 'eklusif',
            default => 'eklusif',
        };
    }

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
                                $product = Product::withoutGlobalScope('product_cabang')->find($state);
                                $set('unit_price', $product->sell_price);
                                $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax'), $get('tipe_pajak') ?? null));
                            })
                            ->helperText(function ($get) {
                                if (!$get('product_id')) return null;
                                
                                $inventoryStock = \App\Models\InventoryStock::freeQtyFor($get('product_id'));
                                
                                return "Stok bebas: " . number_format($inventoryStock, 0, ',', '.');
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
                                $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $state, $get('tax'), $get('tipe_pajak') ?? null));
                            })
                            ->helperText(function ($get) {
                                if (!$get('product_id') || !$get('quantity')) return null;
                                
                                $inventoryStock = \App\Models\InventoryStock::freeQtyFor($get('product_id'));
                                
                                $quantity = (float) $get('quantity');
                                
                                if ($inventoryStock < $quantity) {
                                    return "⚠️ Stok bebas tidak mencukupi. Tersedia: " . number_format($inventoryStock, 0, ',', '.');
                                } else {
                                    return "✅ Stok bebas: " . number_format($inventoryStock, 0, ',', '.');
                                }
                            })
                            ->rule(function ($get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if (!$get('product_id')) return;
                                    
                                    $inventoryStock = \App\Models\InventoryStock::freeQtyFor($get('product_id'));
                                    
                                    if ($inventoryStock < $value) {
                                        $fail('Quantity melebihi stok bebas (' . number_format($inventoryStock, 0, ',', '.') . ')');
                                    }
                                };
                            })
                            ->required()
                            ->default(0),
                        TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->reactive()
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record) {
                                    $component->state(number_format((float) $record->unit_price, 0, ',', '.'));
                                }
                            })
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $state, $get('tipe_pajak') ?? null));
                            })
                            ->indonesianMoney(),
                        TextInput::make('discount')
                            ->label('Discount')
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax'), self::normalizeTaxTypeValue($get('tipe_pajak') ?? null)));
                            })
                            ->indonesianMoney(),
                        Select::make('tipe_pajak')
                            ->label('Tipe Pajak')
                            ->options([
                                'none' => 'Non Pajak',
                                'inklusif' => 'Inklusif',
                                'eklusif' => 'Eklusif',
                            ])
                            ->default('eklusif')
                            ->reactive()
                            ->afterStateHydrated(function ($component, $state) {
                                $component->state(self::normalizeTaxTypeValue($state));
                            })
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $normalized = self::normalizeTaxTypeValue($state);
                                $set('tax', $normalized === 'none' ? 0 : TaxSetting::activeRate('PPN'));
                                $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax'), $normalized));
                            }),
                        TextInput::make('tax')
                            ->label('Tax')
                            ->reactive()
                            ->disabled()
                            ->readOnly()
                            ->default(fn () => TaxSetting::activeRate('PPN'))
                            ->indonesianMoney(),
                        TextInput::make('subtotal')
                            ->label('Sub Total')
                            ->reactive()
                            ->readOnly()
                            ->default(0)
                            ->indonesianMoney()
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                        if ($record->warehouse_id) {
                            $inventoryStock = \App\Models\InventoryStock::where('product_id', $record->product_id)
                                ->where(function ($query) use ($record) {
                                    $query->where('warehouse_id', $record->warehouse_id)
                                          ->orWhere('rak_id', $record->rak_id);
                                })
                                ->first();

                            $availableStock = $inventoryStock ? $inventoryStock->free_qty : 0;
                        } else {
                            $availableStock = \App\Models\InventoryStock::freeQtyFor($record->product_id);
                        }

                        if ($availableStock < $state) {
                            return 'danger'; // Red if quantity exceeds available stock
                        }
                        return 'primary'; // Blue for normal quantity
                    })
                    ->sortable(),
                TextColumn::make('available_stock')
                    ->label('Stok Bebas')
                    ->getStateUsing(function ($record) {
                        if ($record->warehouse_id) {
                            $inventoryStock = \App\Models\InventoryStock::where('product_id', $record->product_id)
                                ->where(function ($query) use ($record) {
                                    $query->where('warehouse_id', $record->warehouse_id)
                                          ->orWhere('rak_id', $record->rak_id);
                                })
                                ->first();

                            return $inventoryStock ? $inventoryStock->free_qty : 0;
                        }

                        // Multi-warehouse / no specific warehouse: return total available across all warehouses
                        $stocks = \App\Models\InventoryStock::where('product_id', $record->product_id)
                            ->get();
                        return (int) $stocks->sum('free_qty');
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
                    ->rupiah()
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
                    ->formatStateUsing(function ($record) {
                        $hasil = HelperController::hitungSubtotal($record->quantity, $record->unit_price, $record->discount, $record->tax, $record->tipe_pajak ?? null);
                        return \App\Helpers\MoneyHelper::rupiah($hasil);
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
