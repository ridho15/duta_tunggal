<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\Pages\ViewProduct;
use App\Filament\Resources\ProductResource\RelationManagers\InventoryStockRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\StockMovementRelationManager;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Services\ProductService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Grid;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $modelLabel = 'Produk';

    protected static ?string $pluralModelLabel = 'Produk';

    protected static ?int $navigationSort = 7;

    /**
     * Cache untuk daftar opsi akun (per tipe)
     * @var array<string, array<int, string>>
     */
    protected static array $coaOptionsCache = [];

    /**
     * Cache untuk mapping kode COA ke ID
     * @var array<string, int|null>
     */
    protected static array $coaIdCache = [];

    /**
     * Kode default akun produk berbasis best practice ERP
     * @var array<string, string>
     */
    protected static array $defaultProductAccountCodes = [
        'inventory' => '1140.10',
        'sales' => '4100.10',
        'sales_return' => '4120.10',
        'sales_discount' => '4110.10',
    'goods_delivery' => '1140.20',
        'cogs' => '5100.10',
        'purchase_return' => '5120.10',
        'unbilled_purchase' => '2190.10',
        'temporary_procurement' => '2100.10',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Product')
                    ->schema([
                        TextInput::make('sku')
                            ->label('SKU')
                            ->validationMessages([
                                'required' => 'SKU tidak boleh kosong',
                                'unique' => 'SKU sudah digunakan !',
                                'max' => 'SKU terlalu panjang'
                            ])
                            ->reactive()
                            ->suffixAction(ActionsAction::make('generateSku')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate SKU')
                                ->action(function ($set, $get, $state) {
                                    $productService = app(ProductService::class);
                                    $set('sku', $productService->generateSku());
                                }))
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'SKU tidak boleh kosong',
                                'unique' => 'SKU sudah digunakan !',
                                'max' => 'SKU terlalu panjang'
                            ])
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
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->preload()
                            ->searchable()
                            ->nullable()
                            ->relationship('supplier', 'name')
                            ->getOptionLabelFromRecordUsing(function (Supplier $supplier) {
                                return "({$supplier->code}) {$supplier->name}";
                            })
                            ->helperText('Pilih supplier untuk produk ini (opsional)'),
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
                            ->indonesianMoney()
                            ->default(0),
                        TextInput::make('sell_price')
                            ->label('Harga Jual (Rp)')
                            ->required()
                            ->indonesianMoney()
                            ->default(0),
                        TextInput::make('biaya')
                            ->label('Biaya (Rp)')
                            ->required()
                            ->indonesianMoney()
                            ->default(0),
                        TextInput::make('harga_batas')
                            ->label('Harga Batas (%)')
                            ->numeric()
                            ->default(0),
                        TextInput::make('item_value')
                            ->label('Item Value (Rp)')
                            ->numeric()
                            ->indonesianMoney()
                            ->default(0),
                        Radio::make('tipe_pajak')
                            ->inlineLabel()
                            ->label('Tipe Pajak Produk')
                            ->options([
                                'Non Pajak' => 'Non Pajak',
                                'Inklusif' => 'Inklusif',
                                'Eksklusif' => 'Eksklusif',
                            ])
                            ->default('Inklusif'),
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
                            ->required()
                            ->validationMessages([
                                'required' => 'Kode merek tidak boleh kosong',
                                'max' => 'Kode merek terlalu panjang'
                            ])
                            ->label('Kode Merk')
                            ->maxLength(50),
                        Select::make('uom_id')
                            ->label('Satuan')
                            ->preload()
                            ->validationMessages([
                                'required' => 'Satuan belum dipilih',
                                'exists' => "Satuan tidak tersedia"
                            ])
                            ->searchable()
                            ->relationship('uom', 'name')
                            ->getOptionLabelFromRecordUsing(function (UnitOfMeasure $uom) {
                                return "{$uom->name} ({$uom->abbreviation})";
                            })
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
                                    ->getOptionLabelFromRecordUsing(function (UnitOfMeasure $uom) {
                                        return "{$uom->name} ({$uom->abbreviation})";
                                    })
                                    ->required(),
                                TextInput::make('nilai_konversi')
                                    ->label('Nilai Konversi')
                                    ->numeric()
                                    ->required(),
                            ]),

                        Toggle::make('is_manufacture')
                            ->label('Diproduksi (Barang Jadi)')
                            ->helperText('Centang jika produk ini adalah hasil produksi (barang jadi siap jual)')
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                // Jika dicentang sebagai barang jadi, pastikan bukan bahan baku
                                if ($state) {
                                    $set('is_raw_material', false);
                                    $finishedGoodsCoa = ChartOfAccount::where('code', '1140.03')->first();
                                    if ($finishedGoodsCoa) {
                                        $set('inventory_coa_id', $finishedGoodsCoa->id);
                                    }
                                } elseif (!$get('is_raw_material')) {
                                    // Jika tidak diproduksi dan bukan bahan baku, reset COA
                                    $set('inventory_coa_id', null);
                                }
                            }),
                        Toggle::make('is_raw_material')
                            ->label('Bahan Baku')
                            ->helperText('Centang jika produk ini adalah bahan baku untuk produksi')
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                // Jika dicentang sebagai bahan baku, pastikan bukan barang jadi
                                if ($state) {
                                    $set('is_manufacture', false);
                                    $rawMaterialCoa = ChartOfAccount::where('code', '1140.01')->first();
                                    if ($rawMaterialCoa) {
                                        $set('inventory_coa_id', $rawMaterialCoa->id);
                                    }
                                } elseif (!$get('is_manufacture')) {
                                    // Jika bukan bahan baku dan tidak diproduksi, reset COA
                                    $set('inventory_coa_id', null);
                                }
                            }),
                        Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->helperText('Nonaktifkan produk untuk menyembunyikan dari transaksi')
                            ->reactive(),
                    ]),
                Fieldset::make('Akun Perkiraan')
                    ->columns(2)
                    ->schema([
                        Select::make('inventory_coa_id')
                            ->label('Persediaan')
                            ->helperText('Akun untuk mencatat persediaan produk ini.')
                            ->options(fn () => self::getCoaOptions('Asset'))
                            ->default(fn () => self::getCoaIdByCode(self::$defaultProductAccountCodes['inventory'] ?? null))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->reactive(),
                        Select::make('sales_coa_id')
                            ->label('Penjualan')
                            ->helperText('Akun penjualan ketika produk dijual.')
                            ->options(fn () => self::getCoaOptions('Revenue'))
                            ->default(fn () => self::getCoaIdByCode(self::$defaultProductAccountCodes['sales'] ?? null))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('sales_return_coa_id')
                            ->label('Retur Penjualan')
                            ->helperText('Akun yang digunakan saat retur penjualan produk.')
                            ->options(fn () => self::getCoaOptions('Revenue'))
                            ->default(fn () => self::getCoaIdByCode(self::$defaultProductAccountCodes['sales_return'] ?? null))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('sales_discount_coa_id')
                            ->label('Diskon Penjualan')
                            ->helperText('Akun untuk diskon penjualan produk.')
                            ->options(fn () => self::getCoaOptions('Revenue'))
                            ->default(fn () => self::getCoaIdByCode(self::$defaultProductAccountCodes['sales_discount'] ?? null))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('goods_delivery_coa_id')
                            ->label('Barang Terkirim')
                            ->helperText('Akun penampung barang terkirim yang belum diterima pelanggan.')
                            ->options(fn () => self::getCoaOptions('Asset'))
                            ->default(fn () => self::getCoaIdByCode(self::$defaultProductAccountCodes['goods_delivery'] ?? null))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('cogs_coa_id')
                            ->label('Beban Pokok Penjualan')
                            ->helperText('Akun beban pokok untuk produk ini.')
                            ->options(fn () => self::getCoaOptions('Expense'))
                            ->default(fn () => self::getCoaIdByCode(self::$defaultProductAccountCodes['cogs'] ?? null))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('purchase_return_coa_id')
                            ->label('Retur Pembelian')
                            ->helperText('Akun ketika terjadi retur pembelian produk.')
                            ->options(fn () => self::getCoaOptions('Expense'))
                            ->default(fn () => self::getCoaIdByCode(self::$defaultProductAccountCodes['purchase_return'] ?? null))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('unbilled_purchase_coa_id')
                            ->label('Pembelian Belum Tertagih')
                            ->helperText('Akun kewajiban ketika barang sudah diterima namun belum ditagih.')
                            ->options(fn () => self::getCoaOptions('Liability'))
                            ->default(fn () => self::getCoaIdByCode(self::$defaultProductAccountCodes['unbilled_purchase'] ?? null))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('temporary_procurement_coa_id')
                            ->label('Pos Sementara Pengadaan')
                            ->helperText('Akun posisi sementara untuk produk selama proses pengadaan. Akan di-zero ketika pengadaan selesai.')
                            ->options(fn () => self::getCoaOptions('Asset'))
                            ->default(fn () => self::getCoaIdByCode(self::$defaultProductAccountCodes['temporary_procurement'] ?? null))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columnSpanFull()
            ]);
    }

    /**
     * Ambil daftar opsi COA yang sudah diformat "kode - nama".
     */
    protected static function getCoaOptions(?string $type = null): array
    {
        $cacheKey = $type ?? 'all';

        if (! array_key_exists($cacheKey, self::$coaOptionsCache)) {
            $query = ChartOfAccount::query()
                ->where('is_active', true)
                ->orderBy('code');

            if ($type) {
                $query->where('type', $type);
            }

            self::$coaOptionsCache[$cacheKey] = $query
                ->get()
                ->mapWithKeys(fn (ChartOfAccount $coa) => [$coa->id => "{$coa->code} - {$coa->name}"])
                ->toArray();
        }

        return self::$coaOptionsCache[$cacheKey];
    }

    /**
     * Ambil ID COA berdasarkan kode dengan cache sederhana.
     */
    protected static function getCoaIdByCode(?string $code): ?int
    {
        if (! $code) {
            return null;
        }

        if (! array_key_exists($code, self::$coaIdCache)) {
            self::$coaIdCache[$code] = ChartOfAccount::where('code', $code)->value('id');
        }

        return self::$coaIdCache[$code];
    }

    /**
     * Format tampilan COA menjadi "kode - nama".
     */
    protected static function formatCoa(?ChartOfAccount $coa): string
    {
        if (! $coa || empty($coa->code)) {
            return '-';
        }

        return "{$coa->code} - {$coa->name}";
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
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->supplier) {
                            return "({$record->supplier->code}) {$record->supplier->name}";
                        }
                        return '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('sell_price')
                    ->sortable()
                    ->searchable()
                    ->money('IDR')
                    ->label('Harga Jual (Rp)'),
                TextColumn::make('harga_batas')
                    ->label('Harga Batas (%)')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('cost_price')
                    ->label('Cost Price (Rp)')
                    ->money('IDR')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('biaya')
                    ->money('IDR')
                    ->label('Biaya (Rp)')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('item_value')->label('Item Value (Rp)')
                    ->money('IDR')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('pajak')
                    ->label('Pajak (%)'),
                TextColumn::make('tipe_pajak')
                    ->badge()->label('Tipe Pajak'),
                TextColumn::make('productCategory.name')
                    ->label('Kategori'),
                IconColumn::make('is_manufacture')
                    ->label('Diproduksi')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: false),
                IconColumn::make('is_raw_material')
                    ->label('Bahan Baku')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('inventoryCoa.name')
                    ->label('Akun Persediaan')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->inventoryCoa) {
                            return $record->inventoryCoa->code . ' - ' . $record->inventoryCoa->name;
                        }
                        return '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('salesCoaDisplay')
                    ->label('Akun Penjualan')
                    ->formatStateUsing(fn ($state, Product $record) => self::formatCoa($record->salesCoa))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('salesReturnCoaDisplay')
                    ->label('Akun Retur Penjualan')
                    ->formatStateUsing(fn ($state, Product $record) => self::formatCoa($record->salesReturnCoa))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('salesDiscountCoaDisplay')
                    ->label('Akun Diskon Penjualan')
                    ->formatStateUsing(fn ($state, Product $record) => self::formatCoa($record->salesDiscountCoa))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('goodsDeliveryCoaDisplay')
                    ->label('Akun Barang Terkirim')
                    ->formatStateUsing(fn ($state, Product $record) => self::formatCoa($record->goodsDeliveryCoa))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cogsCoaDisplay')
                    ->label('Akun Beban Pokok')
                    ->formatStateUsing(fn ($state, Product $record) => self::formatCoa($record->cogsCoa))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchaseReturnCoaDisplay')
                    ->label('Akun Retur Pembelian')
                    ->formatStateUsing(fn ($state, Product $record) => self::formatCoa($record->purchaseReturnCoa))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unbilledPurchaseCoaDisplay')
                    ->label('Akun Pembelian Belum Tertagih')
                    ->formatStateUsing(fn ($state, Product $record) => self::formatCoa($record->unbilledPurchaseCoa))
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
            ])
            ->filters([
                Filter::make('harga_jual_range')
                    ->label('Filter Harga Jual')
                    ->form([
                        Grid::make(2)->schema([
                            TextInput::make('harga_jual_min')
                                ->label('Harga Jual Minimum')
                                ->numeric()
                                ->indonesianMoney(),
                            TextInput::make('harga_jual_max')
                                ->label('Harga Jual Maximum')
                                ->numeric()
                                ->indonesianMoney(),
                        ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['harga_jual_min'],
                                fn(Builder $query, $price): Builder => $query->where('sell_price', '>=', $price),
                            )
                            ->when(
                                $data['harga_jual_max'],
                                fn(Builder $query, $price): Builder => $query->where('sell_price', '<=', $price),
                            );
                    }),

                Filter::make('cost_price_range')
                    ->label('Filter Harga Beli')
                    ->form([
                        Grid::make(2)->schema([
                            TextInput::make('cost_price_min')
                                ->label('Harga Beli Minimum')
                                ->numeric()
                                ->indonesianMoney(),
                            TextInput::make('cost_price_max')
                                ->label('Harga Beli Maximum')
                                ->numeric()
                                ->indonesianMoney(),
                        ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['cost_price_min'],
                                fn(Builder $query, $price): Builder => $query->where('cost_price', '>=', $price),
                            )
                            ->when(
                                $data['cost_price_max'],
                                fn(Builder $query, $price): Builder => $query->where('cost_price', '<=', $price),
                            );
                    }),

                Filter::make('biaya_range')
                    ->label('Filter Biaya')
                    ->form([
                        Grid::make(2)->schema([
                            TextInput::make('biaya_min')
                                ->label('Biaya Minimum')
                                ->numeric()
                                ->indonesianMoney(),
                            TextInput::make('biaya_max')
                                ->label('Biaya Maximum')
                                ->numeric()
                                ->indonesianMoney(),
                        ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['biaya_min'],
                                fn(Builder $query, $price): Builder => $query->where('biaya', '>=', $price),
                            )
                            ->when(
                                $data['biaya_max'],
                                fn(Builder $query, $price): Builder => $query->where('biaya', '<=', $price),
                            );
                    }),

                SelectFilter::make('is_active')
                    ->label('Filter Status')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Nonaktif',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->where('is_active', $data['value']);
                    }),

                SelectFilter::make('is_raw_material')
                    ->label('Filter Tipe Produk')
                    ->options([
                        '1' => 'Bahan Baku',
                        '0' => 'Produksi',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->where('is_raw_material', $data['value']);
                    }),

                SelectFilter::make('supplier_id')
                    ->label('Filter Supplier')
                    ->relationship('supplier', 'name')
                    ->getOptionLabelFromRecordUsing(function (Supplier $supplier) {
                        return "({$supplier->code}) {$supplier->name}";
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                    Action::make('cetakLabel')
                        ->label('Cetak Label / Print Barcode')
                        ->color('success')
                        ->icon('heroicon-o-printer')
                        ->form([
                            Fieldset::make('Pengaturan Print Barcode')
                                ->schema([
                                    Select::make('print_size')
                                        ->label('Ukuran Print')
                                        ->required()
                                        ->options([
                                            'extra-small' => 'Extra Small (8x10 per halaman)',
                                            'small' => 'Small (5x10 per halaman)',
                                            'medium' => 'Medium (4x8 per halaman)',
                                            'standard' => 'Standard (3x10 per halaman)',
                                            'large' => 'Large (2x6 per halaman)'
                                        ])
                                        ->default('standard')
                                        ->helperText('Pilih ukuran label barcode sesuai kebutuhan'),
                                    Select::make('paper_size')
                                        ->label('Ukuran Kertas')
                                        ->required()
                                        ->options([
                                            'A4' => 'A4 (21 x 29.7 cm)',
                                            'Letter' => 'Letter (21.6 x 27.9 cm)',
                                            'Legal' => 'Legal (21.6 x 35.6 cm)'
                                        ])
                                        ->default('A4'),
                                    Select::make('orientation')
                                        ->label('Orientasi Kertas')
                                        ->required()
                                        ->options([
                                            'portrait' => 'Portrait (Tegak)',
                                            'landscape' => 'Landscape (Mendatar)'
                                        ])
                                        ->default('landscape'),
                                    TextInput::make('copies')
                                        ->label('Jumlah Copy per Produk')
                                        ->numeric()
                                        ->default(1)
                                        ->helperText('Berapa banyak label per produk yang akan dicetak'),
                                ])
                        ])
                        ->action(function (array $data, $record) {
                            $printSize = $data['print_size'];
                            $paperSize = $data['paper_size'];
                            $orientation = $data['orientation'];
                            $copies = $data['copies'] ?? 1;

                            // Determine which template to use based on size
                            $templateMap = [
                                'extra-small' => 'pdf.product-barcode-extra-small',
                                'small' => 'pdf.product-barcode-small',
                                'medium' => 'pdf.product-barcode-medium',
                                'standard' => 'pdf.product-single-barcode',
                                'large' => 'pdf.product-barcode-large'
                            ];

                            $template = $templateMap[$printSize] ?? 'pdf.product-single-barcode';

                            // Create PDF with selected settings
                            $pdf = Pdf::loadView($template, [
                                'product' => $record,
                                'copies' => $copies
                            ])->setPaper($paperSize, $orientation);

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Barcode_' . $record->sku . '_' . $printSize . '.pdf');
                        }),
                    Action::make('kalkulasiItemValue')
                        ->label('Kalkulasi Item Value')
                        ->icon('heroicon-o-calculator')
                        ->color('warning')
                        ->modalCancelActionLabel('Tutup')
                        ->modalSubmitAction(false)
                        ->modalContentFooter(function ($record) {
                            $listPurchaseOrderItem = PurchaseOrderItem::where('product_id', $record->id)
                                ->whereHas('purchaseOrder', function ($query) {
                                    $query->where('status', 'completed');
                                })->get();

                            return view('filament.custom.modal-product', [
                                'listPurchaseOrderItem' => $listPurchaseOrderItem
                            ]);
                        })
                        ->form(function ($record) {
                            return [
                                Fieldset::make('Kalkulasi')
                                    ->columnSpanFull()
                                    ->columns(2)
                                    ->schema([
                                        Select::make('cabang_id')
                                            ->label('Cabang')
                                            ->preload()
                                            ->searchable(['kode', 'nama'])
                                            ->required()
                                            ->reactive()
                                            ->disabled()
                                            ->validationMessages([
                                                'required' => 'Cabang belum dipilih'
                                            ])
                                            ->default(function ($record) {
                                                return $record->cabang_id;
                                            })
                                            ->relationship('cabang', 'kode')
                                            ->getOptionLabelFromRecordUsing(function (Cabang $cabang) {
                                                return "({$cabang->kode}) {$cabang->nama}";
                                            }),
                                        Select::make('product_id')
                                            ->label('Product')
                                            ->preload()
                                            ->reactive()
                                            ->disabled()
                                            ->searchable(['name', 'sku'])
                                            ->afterStateUpdated(function ($set, $get, $state) {
                                                $product = Product::find($state);
                                                if ($product) {
                                                    $set('biaya', $product->biaya);
                                                    $set('tipe_pajak', $product->tipe_pajak);
                                                    $set('pajak', $product->pajak);
                                                    $set('item_value', $product->item_value);
                                                }
                                            })
                                            ->default(function ($record) {
                                                return $record->id;
                                            })
                                            ->helperText(function ($state) {
                                                $product = Product::find($state);
                                                if ($product) {
                                                    return "Produk : ({$product->sku}) {$product->name}";
                                                }

                                                return "Produk : ";
                                            })
                                            ->options(function ($get) {
                                                return Product::where('cabang_id', $get('cabang_id'))->get()->pluck('sku', 'id');
                                            })
                                            ->required(),
                                        TextInput::make('biaya')
                                            ->label('Biaya (Rp)')
                                            ->indonesianMoney()
                                            ->numeric()
                                            ->required()
                                            ->disabled()
                                            ->default(function ($record) {
                                                return $record->biaya;
                                            })
                                            ->reactive()
                                            ->default(0),
                                        Radio::make('tipe_pajak')
                                            ->label('Tipe Pajak')
                                            ->reactive()
                                            ->inline()
                                            ->default(function ($record) {
                                                return $record->tipe_pajak;
                                            })
                                            ->disabled()
                                            ->options([
                                                'Non Pajak' => 'Non Pajak',
                                                'Inklusif' => 'Inklusif',
                                                'Eksklusif' => 'Eklusif'
                                            ])
                                            ->required(),
                                        TextInput::make('pajak')
                                            ->label('Pajak (%)')
                                            ->numeric()
                                            ->default(0)
                                            ->disabled()
                                            ->default(function ($record) {
                                                return $record->pajak;
                                            })
                                            ->suffix('%'),
                                        TextInput::make('item_value')
                                            ->label('Item Value')
                                            ->numeric()
                                            ->disabled()
                                            ->default(function ($record) {
                                                return $record->item_value;
                                            })
                                            ->default(0)
                                            ->reactive()
                                            ->indonesianMoney(),
                                    ])
                            ];
                        }),

                    Action::make('toggle_active')
                        ->label(fn(Product $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                        ->icon(fn(Product $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn(Product $record): string => $record->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->modalHeading(fn(Product $record): string => $record->is_active ? 'Nonaktifkan Produk' : 'Aktifkan Produk')
                        ->modalDescription(fn(Product $record): string => $record->is_active
                            ? 'Apakah Anda yakin ingin menonaktifkan produk ini? Produk yang nonaktif tidak akan muncul dalam transaksi baru.'
                            : 'Apakah Anda yakin ingin mengaktifkan produk ini?')
                        ->action(function (Product $record): void {
                            $record->update(['is_active' => !$record->is_active]);

                            \Filament\Notifications\Notification::make()
                                ->title($record->is_active ? 'Produk Diaktifkan' : 'Produk Dinonaktifkan')
                                ->body("Produk {$record->name} berhasil " . ($record->is_active ? 'diaktifkan' : 'dinonaktifkan'))
                                ->success()
                                ->send();
                        })
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    Action::make('bulk_activate')
                        ->label('Aktifkan Produk')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aktifkan Produk Terpilih')
                        ->modalDescription('Apakah Anda yakin ingin mengaktifkan semua produk yang dipilih?')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (!$record->is_active) {
                                    $record->update(['is_active' => true]);
                                    $count++;
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Produk Diaktifkan')
                                ->body("{$count} produk berhasil diaktifkan")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Action::make('bulk_deactivate')
                        ->label('Nonaktifkan Produk')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Nonaktifkan Produk Terpilih')
                        ->modalDescription('Apakah Anda yakin ingin menonaktifkan semua produk yang dipilih? Produk yang nonaktif tidak akan muncul dalam transaksi baru.')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->is_active) {
                                    $record->update(['is_active' => false]);
                                    $count++;
                                }
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Produk Dinonaktifkan')
                                ->body("{$count} produk berhasil dinonaktifkan")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Bulk barcode print action
                    Action::make('bulk_print_barcode')
                        ->label('Print Barcode (Bulk)')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->form([
                            Fieldset::make('Pengaturan Print Barcode Bulk')
                                ->schema([
                                    Select::make('print_size')
                                        ->label('Ukuran Print')
                                        ->required()
                                        ->options([
                                            'extra-small' => 'Extra Small (8x10 per halaman)',
                                            'small' => 'Small (5x10 per halaman)',
                                            'medium' => 'Medium (4x8 per halaman)',
                                            'standard' => 'Standard (3x10 per halaman)',
                                            'large' => 'Large (2x6 per halaman)'
                                        ])
                                        ->default('standard')
                                        ->helperText('Pilih ukuran label barcode sesuai kebutuhan'),
                                    Select::make('paper_size')
                                        ->label('Ukuran Kertas')
                                        ->required()
                                        ->options([
                                            'A4' => 'A4 (21 x 29.7 cm)',
                                            'Letter' => 'Letter (21.6 x 27.9 cm)',
                                            'Legal' => 'Legal (21.6 x 35.6 cm)'
                                        ])
                                        ->default('A4'),
                                    Select::make('orientation')
                                        ->label('Orientasi Kertas')
                                        ->required()
                                        ->options([
                                            'portrait' => 'Portrait (Tegak)',
                                            'landscape' => 'Landscape (Mendatar)'
                                        ])
                                        ->default('landscape'),
                                    TextInput::make('copies_per_product')
                                        ->label('Jumlah Copy per Produk')
                                        ->numeric()
                                        ->default(1)
                                        ->helperText('Berapa banyak label per produk yang akan dicetak'),
                                ])
                        ])
                        ->action(function (array $data, $records) {
                            $products = collect($records);
                            $printSize = $data['print_size'];
                            $paperSize = $data['paper_size'];
                            $orientation = $data['orientation'];
                            $copiesPerProduct = $data['copies_per_product'] ?? 1;

                            // Determine which template to use based on size
                            $templateMap = [
                                'extra-small' => 'pdf.product-barcode-extra-small',
                                'small' => 'pdf.product-barcode-small',
                                'medium' => 'pdf.product-barcode-medium',
                                'standard' => 'pdf.product-barcode',
                                'large' => 'pdf.product-barcode-large'
                            ];

                            $template = $templateMap[$printSize] ?? 'pdf.product-barcode';

                            // Create PDF with selected settings
                            $pdf = Pdf::loadView($template, [
                                'listProduct' => $products,
                                'print_size' => $printSize,
                                'copies_per_product' => $copiesPerProduct
                            ])->setPaper($paperSize, $orientation);

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Bulk_Barcode_' . count($products) . '_products_' . $printSize . '.pdf');
                        })
                        ->deselectRecordsAfterCompletion(),
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
