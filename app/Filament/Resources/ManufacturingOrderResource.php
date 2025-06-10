<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManufacturingOrderResource\Pages;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManufacturingOrderResource extends Resource
{
    protected static ?string $model = ManufacturingOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Manufacturing Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Manufacturing Order')
                    ->schema([
                        TextInput::make('mo_number')
                            ->required()
                            ->maxLength(255),
                        Select::make('product_id')
                            ->required()
                            ->label('Product')
                            ->preload()
                            ->searchable()
                            ->relationship('product', 'name')
                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                return "({$product->sku}) {$product->name}";
                            }),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                DateTimePicker::make('start_date'),
                                DateTimePicker::make('end_date')
                            ]),
                        Repeater::make('manufacturingOrderMaterial')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(4)
                            ->schema([
                                Select::make('material_id')
                                    ->label('Material')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->relationship('material', 'sku')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    }),
                                TextInput::make('qty_required')
                                    ->label('Quantity Required')
                                    ->numeric()
                                    ->required()
                                    ->default(0),
                                TextInput::make('qty_used')
                                    ->label('Quantity Used')
                                    ->numeric()
                                    ->required()
                                    ->default(0),
                                Select::make('warehouse_id')
                                    ->label('Warehouse')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->relationship('warehouse', 'name')
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mo_number')
                    ->searchable(),
                TextColumn::make('product.sku')
                    ->searchable(),
                TextColumn::make('product.name')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status'),
                TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->dateTime()
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManufacturingOrders::route('/'),
            'create' => Pages\CreateManufacturingOrder::route('/create'),
            'edit' => Pages\EditManufacturingOrder::route('/{record}/edit'),
        ];
    }
}
