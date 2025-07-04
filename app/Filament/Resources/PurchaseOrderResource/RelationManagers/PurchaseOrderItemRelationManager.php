<?php

namespace App\Filament\Resources\PurchaseOrderResource\RelationManagers;

use App\Models\Currency;
use App\Models\Product;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $product = Product::find($state);
                                $set('unit_price', $product->cost_price);

                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $get('tax'),
                                    'discount' => $get('discount')
                                ]);
                                $set('subtotal', $subtotal);
                            })
                            ->required(),
                        Select::make('currency_id')
                            ->label('Mata Uang')
                            ->preload()
                            ->searchable()
                            ->required()
                            ->relationship('currency', 'name')
                            ->getOptionLabelFromRecordUsing(function (Currency $currency) {
                                return "{$currency->name} ({$currency->symbol})";
                            })
                            ->validationMessages([
                                'required' => 'Mata uang belum dipilih'
                            ]),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->default(0)
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $get('tax'),
                                    'discount' => $get('discount')
                                ]);
                                $set('subtotal', $subtotal);
                            })
                            ->numeric(),
                        TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $get('tax'),
                                    'discount' => $get('discount')
                                ]);
                                $set('subtotal', $subtotal);
                            })
                            ->prefix('Rp.')
                            ->default(0),
                        TextInput::make('discount')
                            ->label('Discount')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $get('tax'),
                                    'discount' => $get('discount')
                                ]);
                                $set('subtotal', $subtotal);
                            })
                            ->prefix('Rp.')
                            ->default(0),
                        TextInput::make('tax')
                            ->label('Tax')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $subtotal = static::getSubtotal([
                                    'quantity' => $get('quantity'),
                                    'unit_price' => $get('unit_price'),
                                    'tax' => $get('tax'),
                                    'discount' => $get('discount')
                                ]);
                                $set('subtotal', $subtotal);
                            })
                            ->prefix('Rp.')
                            ->default(0),
                        TextInput::make('subtotal')
                            ->label('Sub Total')
                            ->reactive()
                            ->prefix('Rp.')
                            ->default(0)
                            ->readOnly(),
                        Radio::make('tipe_pajak')
                            ->label('Tipe Pajak')
                            ->inline()
                            ->required()
                            ->options([
                                'Non Pajak' => 'Non Pajak',
                                'Inklusif' => 'Inklusif',
                                'Eklusif' => 'Eklusif'
                            ])
                            ->default('default')
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
                TextColumn::make('currency')
                    ->label('Mata Uang')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('currency', function ($query) use ($search) {
                            $query->where('name', 'LIKE', '%' . $search . '%')
                                ->orWhere('symbol', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->formatStateUsing(function ($state) {
                        return "{$state->name} ({$state->symbol})";
                    }),
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
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('tax')
                    ->label('Tax')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('tipe_pajak')
                    ->label('Tipe Pajak')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
