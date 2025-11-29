<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Filament\Resources\StockMovementResource\Pages\ViewStockMovement;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?int $navigationSort = 3;

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
                            ->label('Gudang')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->relationship('warehouse', 'name')
                            ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                return "({$warehouse->kode}) {$warehouse->name}";
                            })
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
                                    'purchase_in' => 'Purchase In',
                                    'sales' => 'Sales',
                                    'transfer_in' => 'Transfer In',
                                    'transfer_out' => 'Transfer Out',
                                    'manufacture_in' => 'Manufacture In',
                                    'manufacture_out' => 'Manufacture Out',
                                    'adjustment_in' => 'Adjustment In',
                                    'adjustment_out' => 'Adjustment Out',
                                ];
                            })
                            ->required(),
                        TextInput::make('reference_id')
                            ->maxLength(255)
                            ->default(null),
                        DatePicker::make('date')
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
                TextColumn::make('warehouse')
                    ->label('Gudang')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('warehouse', function ($query) use ($search) {
                            $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    }),
                TextColumn::make('rak')
                    ->label('Rak')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('rak', function ($query) use ($search) {
                            return $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    }),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->color(function ($state) {
                        return match ($state) {
                            'purchase_in' => 'success',
                            'sales' => 'danger',
                            'transfer_in' => 'primary',
                            'transfer_out' => 'warning',
                            'manufacture_in' => 'info',
                            'manufacture_out' => 'warning',
                            'adjustment_in' => 'secondary',
                            'adjustment_out' => 'danger',
                            default => 'gray',
                        };
                    })->formatStateUsing(function ($state) {
                        return match ($state) {
                            'purchase_in' => 'Purchase In',
                            'sales' => 'Sales',
                            'transfer_in' => 'Transfer In',
                            'transfer_out' => 'Transfer Out',
                            'manufacture_in' => 'Manufacture In',
                            'manufacture_out' => 'Manufacture Out',
                            'adjustment_in' => 'Adjustment In',
                            'adjustment_out' => 'Adjustment Out',
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
                SelectFilter::make('product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Product')
                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                        return "({$product->sku}) {$product->name}";
                    }),
                SelectFilter::make('warehouse')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Gudang')
                    ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                        return "({$warehouse->kode}) {$warehouse->name}";
                    }),
                SelectFilter::make('rak')
                    ->relationship('rak', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Rak')
                    ->getOptionLabelFromRecordUsing(function (Rak $rak) {
                        return "({$rak->code}) {$rak->name}";
                    }),
                SelectFilter::make('type')
                    ->options([
                        'purchase_in' => 'Purchase In',
                        'sales' => 'Sales',
                        'transfer_in' => 'Transfer In',
                        'transfer_out' => 'Transfer Out',
                        'manufacture_in' => 'Manufacture In',
                        'manufacture_out' => 'Manufacture Out',
                        'adjustment_in' => 'Adjustment In',
                        'adjustment_out' => 'Adjustment Out',
                    ])
                    ->multiple()
                    ->label('Type'),
                Filter::make('date')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Date From'),
                        DatePicker::make('date_to')
                            ->label('Date To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->label('Date Range'),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('date', 'DESC');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'view' => ViewStockMovement::route('/{record}'),
            // 'create' => Pages\CreateStockMovement::route('/create'),
            // 'edit' => Pages\EditStockMovement::route('/{record}/edit'),
        ];
    }
}
