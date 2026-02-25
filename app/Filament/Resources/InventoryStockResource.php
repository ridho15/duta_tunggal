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
use Illuminate\Support\Facades\Auth;

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
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = Warehouse::where('status', true);
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->get()->mapWithKeys(function ($warehouse) {
                                    return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                });
                            })
                            ->preload()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = Warehouse::where('status', true)
                                    ->where(function ($q) use ($search) {
                                        $q->where('perusahaan', 'like', "%{$search}%")
                                          ->orWhere('kode', 'like', "%{$search}%");
                                    });
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->limit(50)->get()->mapWithKeys(function ($warehouse) {
                                    return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                });
                            })
                            ->validationMessages([
                                'required' => 'Gudang belum dipilih',
                                'exists' => 'Gudang tidak tersedia'
                            ])
                            ->reactive()
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
            ])
            ->defaultSort('created_at', 'desc')
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Inventory Stock (Stok Inventory)</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Inventory Stock adalah record stok produk yang tersedia di setiap gudang dan rak, melacak quantity available, reserved, dan minimum stock level.</li>' .
                            '<li><strong>Komponen Utama:</strong> <em>Product</em> (produk yang di-stock), <em>Warehouse</em> (gudang penyimpanan), <em>Rak</em> (lokasi spesifik dalam gudang), <em>Qty Available</em> (stok tersedia), <em>Qty Reserved</em> (stok dipesan), <em>Qty Min</em> (minimum stock).</li>' .
                            '<li><strong>Stock Management:</strong> <em>Qty Available</em> = total stock yang bisa digunakan. <em>Qty Reserved</em> = stock yang sudah dipesan tapi belum dikirim. <em>Qty Min</em> = batas minimum stock untuk trigger reorder.</li>' .
                            '<li><strong>Stock Location:</strong> Setiap produk dapat disimpan di multiple warehouse dan rak. Sistem melacak lokasi spesifik untuk memudahkan picking dan putaway.</li>' .
                            '<li><strong>Auto-Update:</strong> Stock otomatis bertambah dari Purchase Receipt (pembelian) dan berkurang dari Material Issue (produksi) atau Delivery Order (penjualan).</li>' .
                            '<li><strong>Validasi:</strong> <em>Stock Check</em> - mencegah pengeluaran stock jika tidak mencukupi. <em>Negative Stock Prevention</em> - sistem tidak mengizinkan stock negatif. <em>Reservation Management</em> - stock yang di-reserve tidak bisa digunakan untuk transaksi lain.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan <em>Purchase Receipt</em> (penambahan stock), <em>Delivery Order</em> (pengurangan stock), <em>Material Issue</em> (penggunaan bahan baku), <em>Stock Movement</em> (transfer antar gudang), dan <em>Stock Adjustment</em> (penyesuaian stock).</li>' .
                            '<li><strong>Actions:</strong> <em>View</em> (lihat detail stock), <em>Edit</em> (ubah informasi stock), <em>Delete</em> (hapus record stock), <em>Stock Movement</em> (transfer ke gudang lain), <em>Stock Adjustment</em> (sesuaikan quantity).</li>' .
                            '<li><strong>Permissions:</strong> <em>view any inventory stock</em>, <em>create inventory stock</em>, <em>update inventory stock</em>, <em>delete inventory stock</em>, <em>restore inventory stock</em>, <em>force-delete inventory stock</em>.</li>' .
                            '<li><strong>Reporting:</strong> Menyediakan data untuk stock valuation, slow moving items, stock aging, ABC analysis, dan inventory turnover ratio.</li>' .
                            '<li><strong>Alerts:</strong> Sistem dapat memberikan warning ketika stock mendekati minimum level atau ada stock yang expired/overdue.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->orderBy('updated_at', 'DESC');
        
        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->whereHas('warehouse', function ($q) use ($user) {
                $q->where('cabang_id', $user->cabang_id);
            });
        }
        
        return $query;
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
