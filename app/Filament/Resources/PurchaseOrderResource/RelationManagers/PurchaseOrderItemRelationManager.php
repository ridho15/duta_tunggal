<?php

namespace App\Filament\Resources\PurchaseOrderResource\RelationManagers;

use App\Models\Product;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseOrderItemRelationManager extends RelationManager
{
    protected static string $relationship = 'PurchaseOrderItem';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Purchasee Order Item')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "{$record->sku} - {$record->name}";
                            })
                            ->relationship('product', 'name')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, $state) {
                                $product = Product::find($state);
                                $set('unit_price', $product->cost_price);
                            })
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->default(0)
                            ->numeric(),
                        TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->reactive()
                            ->prefix('Rp.')
                            ->default(0),
                        TextInput::make('discount')
                            ->label('Discount')
                            ->reactive()
                            ->prefix('Rp.')
                            ->default(0),
                        TextInput::make('tax')
                            ->label('Tax')
                            ->reactive()
                            ->prefix('Rp.')
                            ->default(0),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('Product Name')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('discount')
                    ->label('Discount')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('tax')
                    ->label('Tax')
                    ->money('idr')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
