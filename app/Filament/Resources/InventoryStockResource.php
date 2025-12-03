<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryStockResource\Pages;
use App\Filament\Resources\InventoryStockResource\Pages\ViewInventoryStock;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Warehouse;
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
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryStockResource extends Resource
{
    protected static ?string $model = InventoryStock::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Gudang';

    // Position Gudang as the 6th group
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Inventory Stock')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->preload()
                            ->searchable(['sku', 'name'])
                            ->validationMessages([
                                'required' => 'Product belum dipilih',
                                'exists' => 'Product tidak tersedia'
                            ])
                            ->relationship('product', 'id')
                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                return "({$product->sku}) {$product->name}";
                            })
                            ->required(),
                        Select::make('warehouse_id')
                            ->label('Gudang')
                            ->preload()
                            ->searchable(['kode', 'name'])
                            ->validationMessages([
                                'required' => 'Gudang belum dipilih',
                                'exists' => 'Gudang tidak tersedia'
                            ])
                            ->reactive()
                            ->relationship('warehouse', 'id', function (Builder $query) {
                                $query->where('status', true);
                            })
                            ->getOptionLabelFromRecordUsing(function (Warehouse $warehouse) {
                                return "({$warehouse->kode}) {$warehouse->name}";
                            })
                            ->required()
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $productId = request()->input('product_id');
                                        $warehouseId = $value;
                                        $rakId = request()->input('rak_id');

                                        if ($productId && $warehouseId && $rakId) {
                                            $existing = InventoryStock::where('product_id', $productId)
                                                ->where('warehouse_id', $warehouseId)
                                                ->where('rak_id', $rakId)
                                                ->exists();

                                            if ($existing) {
                                                $fail('Stok inventory untuk kombinasi produk, gudang, dan rak ini sudah ada. Gunakan fitur edit untuk mengubah data yang sudah ada.');
                                            }
                                        }
                                    };
                                }
                            ]),
                        TextInput::make('qty_available')
                            ->required()
                            ->numeric()
                            ->validationMessages([
                                'required' => 'Quantity available tidak boleh kosong'
                            ])
                            ->default(0),
                        TextInput::make('qty_reserved')
                            ->required()
                            ->validationMessages([
                                'required' => 'Quantity reserved tidak boleh kosong'
                            ])
                            ->numeric()
                            ->default(0),
                        TextInput::make('qty_min')
                            ->label('Quantity Minimal')
                            ->required()
                            ->numeric()
                            ->validationMessages([
                                'required' => 'Quantity minimal tidak boleh kosong'
                            ])
                            ->default(0),
                        Select::make('rak_id')
                            ->label('Rak')
                            ->preload()
                            ->searchable(['name', 'code'])
                            ->reactive()
                            ->relationship('rak', 'name', function ($get, Builder $query) {
                                $query->where('warehouse_id', $get('warehouse_id'));
                            })
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "({$record->code}) {$record->name}";
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
                TextColumn::make('warehouse')
                    ->label('Gudang')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('warehouse', function ($query) use ($search) {
                            $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->name}";
                    }),
                TextColumn::make('rak')
                    ->label('Rak')
                    ->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('rak', function (Builder $query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('qty_available')
                    ->label('Quantity Available')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('qty_reserved')
                    ->label('Quantity Reserved')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('qty_min')
                    ->label('Quantity Minimal')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Gudang')
                    ->relationship('warehouse', 'name', function (Builder $query) {
                        $query->where('status', true);
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('rak_id')
                    ->label('Rak')
                    ->relationship('rak', 'name')
                    ->searchable()
                    ->preload(),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('updated_at', 'DESC');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryStocks::route('/'),
            'create' => Pages\CreateInventoryStock::route('/create'),
            'view' => ViewInventoryStock::route('/{record}'),
            'edit' => Pages\EditInventoryStock::route('/{record}/edit'),
        ];
    }
}
