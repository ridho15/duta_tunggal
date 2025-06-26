<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\Pages\ViewProduct;
use App\Filament\Resources\ProductResource\RelationManagers\InventoryStockRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\StockMovementRelationManager;
use App\Models\Cabang;
use App\Models\Product;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;

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
                        TextInput::make('sku')
                            ->label('SKU')
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name')
                            ->required()
                            ->label('Nama Produk')
                            ->maxLength(255),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->relationship('cabang', 'nama')
                            ->getOptionLabelFromRecordUsing(function (Cabang $cabang) {
                                return "({$cabang->kode}) {$cabang->nama}";
                            }),
                        Select::make('product_category_id')
                            ->label('Product Category')
                            ->searchable()
                            ->reactive()
                            ->relationship('productCategory', 'name', function (Builder $query, $get) {
                                $query->where('cabang_id', $get('cabang_id'));
                            })
                            ->preload()
                            ->required(),

                        TextInput::make('cost_price')
                            ->label('Harga Beli Asli (Rp)')
                            ->required()
                            ->numeric()
                            ->prefix('Rp.')
                            ->default(0),
                        TextInput::make('sell_price')
                            ->label('Harga Jual (Rp)')
                            ->required()
                            ->prefix('Rp.')
                            ->numeric()
                            ->default(0),
                        TextInput::make('biaya')
                            ->label('Biaya (Rp)')
                            ->required()
                            ->prefix('Rp.')
                            ->numeric()
                            ->default(0),
                        TextInput::make('harga_batas')
                            ->label('Harga Batas (%)')
                            ->numeric()
                            ->default(0),
                        TextInput::make('item_value')
                            ->label('Item Value (Rp)')
                            ->numeric()
                            ->prefix('Rp.')
                            ->default(0),
                        Radio::make('tipe_pajak')
                            ->inlineLabel()
                            ->label('Tipe Pajak Produk')
                            ->options([
                                'Non Pajak' => 'Non Pajak',
                                'Inklusif' => 'Inklusif',
                                'Eksklusif' => 'Eksklusif',
                            ])
                            ->default('Non Pajak'),
                        TextInput::make('pajak')
                            ->label('Pajak (%)')
                            ->numeric()
                            ->default(0),
                        TextInput::make('jumlah_kelipatan_gudang_besar')
                            ->label('Jumlah Kelipatan di Gudang Besar')
                            ->numeric()
                            ->default(0),
                        TextInput::make('jumlah_jual_kategori_banyak')
                            ->label('Jumlah Jual Kategori Banyak')
                            ->numeric()
                            ->default(0),
                        TextInput::make('kode_merk')
                            ->label('Kode Merk')
                            ->maxLength(50),
                        Select::make('uom_id')
                            ->label('Satuan')
                            ->preload()
                            ->searchable()
                            ->relationship('uom', 'name')
                            ->required(),
                        Textarea::make('description')
                            ->label('Description')
                            ->nullable(),
                        Repeater::make('unitConversions')
                            ->relationship()
                            ->columnSpanFull()
                            ->columns(2)
                            ->label('Konversi Satuan')
                            ->schema([
                                Select::make('uom_id')
                                    ->label('Satuan')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('uom', 'name')
                                    ->required(),
                                TextInput::make('nilai_konversi')
                                    ->label('Nilai Konversi')
                                    ->numeric()
                                    ->required(),
                            ]),

                        Toggle::make('is_asset')
                            ->label('Is Asset')
                            ->reactive(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('Kode')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('cabang')
                    ->label('Cabang')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->nama}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('cabang', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('nama', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('sell_price')
                    ->sortable()
                    ->money('idr')
                    ->label('Harga Jual (Rp)'),
                TextColumn::make('harga_batas')
                    ->label('Harga Batas (%)')
                    ->sortable(),
                TextColumn::make('cost_price')
                    ->label('Cost Price (Rp)')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('biaya')
                    ->money('idr')
                    ->label('Biaya (Rp)'),
                TextColumn::make('item_value')->label('Item Value (Rp)')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('pajak')
                    ->label('Pajak (%)'),
                TextColumn::make('tipe_pajak')
                    ->badge()->label('Tipe Pajak'),
                TextColumn::make('productCategory.name')
                    ->label('Kategori'),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
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
            InventoryStockRelationManager::class,
            StockMovementRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
