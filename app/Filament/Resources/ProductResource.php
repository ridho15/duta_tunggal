<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Product')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(255),
                        Select::make('product_category_id')
                            ->label('Product Category')
                            ->searchable()
                            ->relationship('productCategory', 'name')
                            ->preload()
                            ->required(),
                        TextInput::make('cost_price')
                            ->required()
                            ->numeric()
                            ->prefix('Rp.')
                            ->default(0),
                        TextInput::make('sell_price')
                            ->required()
                            ->prefix('Rp.')
                            ->numeric()
                            ->default(0),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        Select::make('uom_id')
                            ->label('Unit Of Measure')
                            ->searchable()
                            ->preload()
                            ->relationship('uom', 'name')
                            ->required(),
                        Toggle::make('is_asset')
                            ->label('Is Asset')
                            ->reactive(),
                        TextInput::make('usefull_life_years')
                            ->required()
                            ->numeric()
                            ->reactive()
                            ->hidden(function ($get) {
                                if ($get('is_asset')) {
                                    return false;
                                }

                                return true;
                            })
                            ->default(0),
                        TextInput::make('residual_value')
                            ->required()
                            ->numeric()
                            ->hidden(function ($get) {
                                if ($get('is_asset')) {
                                    return false;
                                }

                                return true;
                            })
                            ->default(0),
                        DatePicker::make('purchase_date')
                            ->hidden(function ($get) {
                                if ($get('is_asset')) {
                                    return false;
                                }

                                return true;
                            }),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('productCategory.name')
                    ->label('Product Category')
                    ->searchable(),
                TextColumn::make('cost_price')
                    ->numeric()
                    ->money('idr', true)
                    ->sortable(),
                TextColumn::make('sell_price')
                    ->numeric()
                    ->money('idr', true)
                    ->sortable(),
                TextColumn::make('uom.name')
                    ->label('Unit Of Measure')
                    ->searchable(),
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
                IconColumn::make('is_asset')
                    ->boolean(),
                TextColumn::make('usefull_life_years')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('residual_value')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('purchase_date')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->date()
                    ->sortable(),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
