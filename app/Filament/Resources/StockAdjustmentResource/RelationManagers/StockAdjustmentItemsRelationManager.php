<?php

namespace App\Filament\Resources\StockAdjustmentResource\RelationManagers;

use App\Helpers\MoneyHelper;
use App\Models\Product;
use App\Models\Rak;
use App\Filament\Resources\StockAdjustmentResource;
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

class StockAdjustmentItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('product_id')
                    ->label('Produk')
                    ->options(fn () => StockAdjustmentResource::resolveProductOptions())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->getSearchResultsUsing(fn (string $search) => StockAdjustmentResource::resolveProductOptions($search))
                    ->getOptionLabelUsing(fn ($value): ?string => StockAdjustmentResource::resolveProductLabel(is_numeric($value) ? (int) $value : null))
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $product = Product::find($state);
                            if ($product) {
                                $set('unit_cost', $product->cost_price ?? 0);
                            }
                        }
                    }),

                Select::make('rak_id')
                    ->label('Rak')
                    ->options(function () {
                        $warehouseId = $this->getOwnerRecord()->warehouse_id ?? null;

                        if (!$warehouseId) {
                            return [];
                        }

                        return StockAdjustmentResource::resolveRakOptions($warehouseId);
                    })
                    ->searchable()
                    ->preload()
                    ->getSearchResultsUsing(function (string $search) {
                        $warehouseId = $this->getOwnerRecord()->warehouse_id ?? null;

                        if (!$warehouseId) {
                            return [];
                        }

                        return StockAdjustmentResource::resolveRakOptions($warehouseId, $search);
                    })
                    ->getOptionLabelUsing(fn ($value): ?string => StockAdjustmentResource::resolveRakLabel(is_numeric($value) ? (int) $value : null)),

                TextInput::make('current_qty')
                    ->numeric()
                    ->default(0)
                    ->required(),

                TextInput::make('adjusted_qty')
                    ->label('Qty Setelah Adjustment')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                        $currentQty = $get('current_qty') ?? 0;
                        $adjustedQty = $state ?? 0;
                        $difference = $adjustedQty - $currentQty;
                        $set('difference_qty', $difference);
                    }),

                TextInput::make('difference_qty')
                    ->label('Selisih Qty')
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('unit_cost')
                    ->label('Harga Satuan')
                    ->indonesianMoney()
                    ->default(0)
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                        $differenceQty = $get('difference_qty') ?? 0;
                        $unitCost = \App\Helpers\MoneyHelper::safeParse($state ?? 0);
                        $differenceValue = $differenceQty * $unitCost;
                        $set('difference_value', $differenceValue);
                    }),

                TextInput::make('difference_value')
                    ->label('Nilai Selisih')
                    ->prefix('Rp')
                    ->disabled()
                    ->dehydrated()
                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? number_format((float) MoneyHelper::safeParse($state), 2, ',', '.') : ''),

                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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

                TextColumn::make('current_qty')
                    ->label('Qty Saat Ini')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('adjusted_qty')
                    ->label('Qty Setelah Adj')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('difference_qty')
                    ->label('Selisih Qty')
                    ->numeric()
                    ->color(fn ($record) => $record->difference_qty > 0 ? 'success' : ($record->difference_qty < 0 ? 'danger' : 'gray'))
                    ->sortable(),

                TextColumn::make('unit_cost')
                    ->label('Harga Satuan')
                    ->rupiah()
                    ->sortable(),

                TextColumn::make('difference_value')
                    ->label('Nilai Selisih')
                    ->rupiah()
                    ->color(fn ($record) => $record->difference_value > 0 ? 'success' : ($record->difference_value < 0 ? 'danger' : 'gray'))
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
                ]),
            ]);
    }
}
