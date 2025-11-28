<?php

namespace App\Filament\Resources\StockAdjustmentResource\RelationManagers;

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

class StockAdjustmentItemsRelationManager extends RelationManager
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
                            // You can add logic to get current stock here
                        }
                    }),

                Select::make('rak_id')
                    ->label('Rak')
                    ->options(Rak::pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                TextInput::make('current_qty')
                    ->label('Qty Saat Ini')
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
                    }),

                TextInput::make('difference_value')
                    ->label('Nilai Selisih')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),

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
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('difference_value')
                    ->label('Nilai Selisih')
                    ->money('IDR')
                    ->color(fn ($record) => $record->difference_value > 0 ? 'success' : ($record->difference_value < 0 ? 'danger' : 'gray'))
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
}