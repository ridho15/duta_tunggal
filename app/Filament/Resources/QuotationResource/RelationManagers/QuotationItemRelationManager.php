<?php

namespace App\Filament\Resources\QuotationResource\RelationManagers;

use App\Http\Controllers\HelperController;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class QuotationItemRelationManager extends RelationManager
{
    protected static string $relationship = 'quotationItem';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Quotation Item')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $product = Product::find($state);
                                $set('unit_price', $product->sell_price);
                                $set('total_price', HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax')));
                            })
                            ->relationship('product', 'id')
                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                return "({$product->sku}) {$product->name}";
                            }),
                        TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->numeric()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $set('total_price', HelperController::hitungSubtotal($get('quantity'), $state, $get('discount'), $get('tax')));
                            })
                            ->default(0)
                            ->indonesianMoney(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $set('total_price', HelperController::hitungSubtotal($state, $get('unit_price'), $get('discount'), $get('tax')));
                            })
                            ->reactive()
                            ->default(0),
                        TextInput::make('discount')
                            ->label('Discount')
                            ->numeric()
                            ->maxValue(100)
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $set('total_price', HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $state, $get('tax')));
                            })
                            ->reactive()
                            ->default(0)
                            ->suffix('%'),
                        TextInput::make('tax')
                            ->label('Tax')
                            ->numeric()
                            ->maxValue(100)
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $set('total_price', HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $state));
                            })
                            ->default(0)
                            ->suffix('Rp.'),
                        TextInput::make('total_price')
                            ->label('Total Price')
                            ->numeric()
                            ->reactive()
                            ->default(0)
                            ->indonesianMoney(),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->nullable(),

                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('product')
                    ->label('Proudct')
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    }),
                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->sortable(),
                TextColumn::make('discount')
                    ->label('Discount')
                    ->suffix(' %')
                    ->sortable(),
                TextColumn::make('tax')
                    ->label('Tax')
                    ->suffix(' %')
                    ->sortable(),
                TextColumn::make('total_price')
                    ->label('Total Price')
                    ->money('IDR')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
