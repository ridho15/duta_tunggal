<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryStockResource\Pages;
use App\Models\InventoryStock;
use App\Models\Product;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;

class InventoryStockResource extends Resource
{
    protected static ?string $model = InventoryStock::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Gudang';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Inventory Stock')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->preload()
                            ->searchable()
                            ->relationship('product', 'id')
                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                return "({$product->sku}) {$product->name}";
                            })
                            ->required(),
                        Select::make('warehosue_id')
                            ->label('Warehouse')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->relationship('warehouse', 'name')
                            ->required(),
                        TextInput::make('qty_available')
                            ->required()
                            ->numeric()
                            ->default(0),
                        TextInput::make('qty_reserved')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Select::make('rak_id')
                            ->label('Rak')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->relationship('rak', 'name', function ($get, Builder $query) {
                                $query->where('warehouse_id', $get('warehouse_id'));
                            })
                            ->nullable(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product')
                    ->label('Product')
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('product', function ($query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('warehouse.name')
                    ->searchable()
                    ->label('Warehouse'),
                TextColumn::make('qty_available')
                    ->label('Quantity Available')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('qty_reserved')
                    ->label('Quantity Reserved')
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
                TextColumn::make('rak.name')
                    ->label('Rak')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make()
                ])
            ], position: ActionsPosition::BeforeColumns)
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
            'index' => Pages\ListInventoryStocks::route('/'),
            // 'create' => Pages\CreateInventoryStock::route('/create'),
            // 'edit' => Pages\EditInventoryStock::route('/{record}/edit'),
        ];
    }
}
