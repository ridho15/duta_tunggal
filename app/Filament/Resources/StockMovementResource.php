<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Filament\Resources\StockMovementResource\RelationManagers;
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Warehouse';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Stock Movement')
                    ->schema([
                        Select::make('product_id')
                            ->preload()
                            ->label('Product')
                            ->searchable()
                            ->relationship('product', 'id')
                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                return "({$product->sku}) {$product->name}";
                            })
                            ->required(),
                        Select::make('warehouse_id')
                            ->label('Warehouse')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->relationship('warehouse', 'name')
                            ->required(),
                        Select::make('rak_id')
                            ->label('Rak')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->relationship('rak', 'name', function ($get, Builder $query) {
                                return $query->where('warehouse_id', $get('warehouse_id'));
                            })
                            ->required(),
                        TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Radio::make('type')
                            ->label('Type')
                            ->inlineLabel()
                            ->options(function () {
                                return [
                                    'transfer_in' => 'Transfer In',
                                    'transfer_out' => 'Transfer Out',
                                    'manufacture_in' => 'Manufacture In',
                                    'manufacture_out' => 'Manufacture Out',
                                ];
                            })
                            ->required(),
                        TextInput::make('reference_id')
                            ->maxLength(255)
                            ->default(null),
                        DateTimePicker::make('date')
                            ->required(),
                        Textarea::make('notes')
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
                        $query->whereHas('product', function (Builder $query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable(),
                TextColumn::make('rak.name')
                    ->label('Rak')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->color(function ($state) {
                        return match ($state) {
                            'transfer_in' => 'primary',
                            'transfer_out' => 'warning',
                            'manufacture_in' => 'info',
                            'manufacture_out' => 'warning',
                            default => 'gray',
                        };
                    })->formatStateUsing(function ($state) {
                        return match ($state) {
                            'transfer_in' => 'Transfer In',
                            'transfer_out' => 'Transfer Out',
                            'manufacture_in' => 'Manufacture In',
                            'manufacture_out' => 'Manufacture Out',
                            default => '-'
                        };
                    })
                    ->badge(),
                TextColumn::make('reference_id')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('date')
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
                ViewAction::make()
                    ->color('primary')
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
            'index' => Pages\ListStockMovements::route('/'),
            // 'create' => Pages\CreateStockMovement::route('/create'),
            // 'edit' => Pages\EditStockMovement::route('/{record}/edit'),
        ];
    }
}
