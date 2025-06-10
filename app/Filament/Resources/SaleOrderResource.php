<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleOrderResource\Pages;
use App\Filament\Resources\SaleOrderResource\RelationManagers\SaleOrderItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\Product;
use App\Models\SaleOrder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SaleOrderResource extends Resource
{
    protected static ?string $model = SaleOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Sales Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Sales')
                    ->schema([
                        Select::make('customer_id')
                            ->required()
                            ->label('Customer')
                            ->preload()
                            ->searchable()
                            ->relationship('customer', 'name'),
                        TextInput::make('so_number')
                            ->required()
                            ->maxLength(255),
                        DateTimePicker::make('order_date')
                            ->required(),
                        DateTimePicker::make('delivery_date'),
                        TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->prefix('Rp.')
                            ->required()
                            ->disabled()
                            ->default(0)
                            ->numeric(),
                        Repeater::make('saleOrderItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(3)
                            ->addActionLabel("Add Items")
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
                                    ->default(0)
                                    ->prefix('Rp.')
                            ])
                    ])
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('so_number')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status'),
                TextColumn::make('delivery_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SaleOrderItemRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaleOrders::route('/'),
            'create' => Pages\CreateSaleOrder::route('/create'),
            'edit' => Pages\EditSaleOrder::route('/{record}/edit'),
        ];
    }
}
