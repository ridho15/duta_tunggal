<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages;
use App\Filament\Resources\AssetResource\RelationManagers;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Placeholder;
use Filament\Tables\Enums\ActionsPosition;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Asset Management';

    protected static ?string $navigationLabel = 'Aset Tetap';

    protected static ?string $modelLabel = 'Aset Tetap';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Aset')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Asset')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->default(fn() => \App\Models\Asset::generateAssetCode())
                            ->validationMessages([
                                'required' => 'Kode asset wajib diisi',
                                'unique' => 'Kode asset sudah digunakan',
                                'max' => 'Kode asset maksimal 50 karakter'
                            ]),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Barang')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->validationMessages([
                                'required' => 'Nama barang wajib diisi',
                                'max' => 'Nama barang maksimal 255 karakter'
                            ]),

                        Forms\Components\Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];

                                if (!$user || !in_array('all', $manageType)) {
                                    return \App\Models\Cabang::where('id', $user?->cabang_id)
                                        ->get()
                                        ->mapWithKeys(function ($cabang) {
                                            return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                        });
                                }

                                return \App\Models\Cabang::all()->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                });
                            })
                            ->visible(fn() => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn() => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Cabang wajib dipilih'
                            ])
                            ->reactive(),

                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Tanggal Beli')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->validationMessages([
                                'required' => 'Tanggal beli wajib diisi'
                            ]),

                        Forms\Components\DatePicker::make('usage_date')
                            ->label('Tanggal Pakai')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->validationMessages([
                                'required' => 'Tanggal pakai wajib diisi'
                            ]),

                        Forms\Components\Select::make('product_id')
                            ->label('Product Master')
                            ->relationship('product', 'name')
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->sku . ' - ' . $record->name)
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $cabangId = $get('cabang_id');
                                $query = \App\Models\Product::where('name', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%");

                                if ($cabangId) {
                                    $query->where('cabang_id', $cabangId);
                                }

                                return $query->get()
                                    ->mapWithKeys(fn($product) => [$product->id => $product->sku . ' - ' . $product->name])
                                    ->toArray();
                            })
                            ->options(function (Get $get) {
                                $cabangId = $get('cabang_id');
                                if ($cabangId) {
                                    return \App\Models\Product::where('cabang_id', $cabangId)
                                        ->get()
                                        ->mapWithKeys(fn($product) => [$product->id => $product->sku . ' - ' . $product->name]);
                                }
                                return [];
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Pilih produk master yang akan dijadikan asset. Produk ini akan dilink dengan purchase order item.')
                            ->validationMessages([
                                'required' => 'Produk master wajib dipilih'
                            ])
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if ($state) {
                                    // Auto-fill purchase_order_id and purchase_order_item_id based on selected product
                                    $product = \App\Models\Product::find($state);
                                    if ($product) {
                                        // Find the latest purchase order item for this product
                                        $latestPOItem = \App\Models\PurchaseOrderItem::where('product_id', $state)
                                            ->with('purchaseOrder')
                                            ->orderBy('created_at', 'desc')
                                            ->first();

                                        if ($latestPOItem) {
                                            $set('purchase_order_id', $latestPOItem->purchase_order_id);
                                            $set('purchase_order_item_id', $latestPOItem->id);
                                            // Auto-fill purchase cost from PO item (quantity * unit_price)
                                            $purchaseCost = $latestPOItem->quantity * $latestPOItem->unit_price;
                                            $set('purchase_cost', number_format($purchaseCost, 2, '.', ''));
                                        }
                                    }
                                }

                                // Calculate salvage value when product changes
                                static::calculateSalvageValue($get, $set);
                            }),
                        Forms\Components\Select::make('depreciation_method')
                            ->label('Metode Penyusutan')
                            ->options([
                                'straight_line' => 'Garis Lurus (Straight Line)',
                                'declining_balance' => 'Saldo Menurun Ganda (Double Declining Balance)',
                                'sum_of_years_digits' => 'Jumlah Digit Tahun (Sum of Years\' Digits)',
                                'units_of_production' => 'Unit Produksi (Units of Production)',
                            ])
                            ->default('straight_line')
                            ->required()
                            ->helperText('Garis Lurus: (Biaya aset - nilai sisa) ÷ umur manfaat | Saldo Menurun Ganda: 2 × (1 ÷ umur manfaat) × nilai buku awal | Jumlah Digit Tahun: (Biaya disusutkan) × (sisa masa manfaat ÷ jumlah digit tahun)')
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Metode penyusutan wajib dipilih'
                            ])
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                // Calculate salvage value when depreciation method changes
                                static::calculateSalvageValue($get, $set);

                                static::calculateDepreciation($get, $set);
                            }),
                        Forms\Components\TextInput::make('purchase_cost')
                            ->label('Biaya Aset (Rp)')
                            ->required()
                            ->indonesianMoney()
                            ->stripCharacters(',')
                            ->helperText('Biaya perolehan aset = harga pembelian + biaya pengiriman + biaya instalasi + biaya lainnya. Akan diisi otomatis dari Purchase Order jika tersedia.')
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Biaya aset wajib diisi',
                                'numeric' => 'Biaya aset harus berupa angka'
                            ])
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                // Calculate salvage value when purchase cost changes
                                static::calculateSalvageValue($get, $set);

                                static::calculateDepreciation($get, $set);
                            }),

                        Forms\Components\TextInput::make('useful_life_years')
                            ->label('Umur Manfaat Aset (Tahun)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(5)
                            ->reactive()
                            ->validationMessages([
                                'required' => 'Umur manfaat wajib diisi',
                                'numeric' => 'Umur manfaat harus berupa angka',
                                'min' => 'Umur manfaat minimal 1 tahun'
                            ])
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                // Calculate salvage value when useful life changes
                                static::calculateSalvageValue($get, $set);

                                static::calculateDepreciation($get, $set);
                            }),
                        Forms\Components\TextInput::make('salvage_value')
                            ->label('Nilai Sisa (Rp)')
                            ->indonesianMoney()
                            ->stripCharacters(',')
                            ->default(0)
                            ->readonly()
                            ->helperText('Nilai sisa dihitung otomatis 5% dari biaya perolehan setelah semua field terkait terisi (product, metode penyusutan, biaya aset, umur manfaat).')
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                static::calculateDepreciation($get, $set);
                            }),

                        Forms\Components\Hidden::make('purchase_order_id'),
                        Forms\Components\Hidden::make('purchase_order_item_id'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Chart of Accounts')
                    ->schema([
                        Forms\Components\Select::make('asset_coa_id')
                            ->label('Aset')
                            ->options(
                                ChartOfAccount::whereIn('code', [
                                    '1210.01',
                                    '1210.02',
                                    '1210.03',
                                    '1210.04'
                                ])->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Pilih COA untuk Aset: 1210.01 PERALATAN KANTOR (OE), 1210.02 PERLENGKAPAN KANTOR (FF), 1210.03 KENDARAAN, 1210.04 BANGUNAN')
                            ->validationMessages([
                                'required' => 'COA aset wajib dipilih'
                            ])
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if ($state) {
                                    $assetCoa = ChartOfAccount::find($state);
                                    if ($assetCoa) {
                                        // Auto-select corresponding accumulated depreciation and expense COA
                                        $mapping = [
                                            '1210.01' => ['accumulated' => '1220.01', 'expense' => '6311'],
                                            '1210.02' => ['accumulated' => '1220.02', 'expense' => '6312'],
                                            '1210.03' => ['accumulated' => '1220.03', 'expense' => '6313'],
                                            '1210.04' => ['accumulated' => '1220.04', 'expense' => '6314'],
                                        ];

                                        if (isset($mapping[$assetCoa->code])) {
                                            $accumulatedCoa = ChartOfAccount::where('code', $mapping[$assetCoa->code]['accumulated'])->first();
                                            $expenseCoa = ChartOfAccount::where('code', $mapping[$assetCoa->code]['expense'])->first();

                                            if ($accumulatedCoa) {
                                                $set('accumulated_depreciation_coa_id', $accumulatedCoa->id);
                                            }

                                            if ($expenseCoa) {
                                                $set('depreciation_expense_coa_id', $expenseCoa->id);
                                            }
                                        }
                                    }
                                }
                            }),

                        Forms\Components\Select::make('accumulated_depreciation_coa_id')
                            ->label('Akumulasi Penyusutan')
                            ->options(
                                ChartOfAccount::whereIn('code', [
                                    '1220.01',
                                    '1220.02',
                                    '1220.03',
                                    '1220.04'
                                ])->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Pilih COA untuk Akumulasi Penyusutan: 1220.01 AKUMULASI BIAYA PENYUSUTAN PERALATAN KANTOR (OE), 1220.02 AKUMULASI BIAYA PENYUSUTAN PERLENGKAPAN KANTOR (FF), 1220.03 AKUMULASI BIAYA PENYUSUTAN KENDARAAN, 1220.04 AKUMULASI BIAYA PENYUSUTAN BANGUNAN')
                            ->validationMessages([
                                'required' => 'COA akumulasi penyusutan wajib dipilih'
                            ])
                            ->default(function (Get $get) {
                                $assetCoaId = $get('asset_coa_id');
                                if ($assetCoaId) {
                                    $assetCoa = ChartOfAccount::find($assetCoaId);
                                    if ($assetCoa) {
                                        $mapping = [
                                            '1210.01' => '1220.01',
                                            '1210.02' => '1220.02',
                                            '1210.03' => '1220.03',
                                            '1210.04' => '1220.04',
                                        ];

                                        if (isset($mapping[$assetCoa->code])) {
                                            $accumulatedCoa = ChartOfAccount::where('code', $mapping[$assetCoa->code])->first();
                                            return $accumulatedCoa?->id;
                                        }
                                    }
                                }
                                return null;
                            }),

                        Forms\Components\Select::make('depreciation_expense_coa_id')
                            ->label('Beban Penyusutan')
                            ->options(
                                ChartOfAccount::whereIn('code', [
                                    '6311',
                                    '6312',
                                    '6313',
                                    '6314'
                                ])->get()->mapWithKeys(fn($coa) => [$coa->id => $coa->code . ' - ' . $coa->name])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Pilih COA untuk Beban Penyusutan: 6311 BIAYA PENYUSUTAN PERALATAN KANTOR (OE), 6312 BIAYA PENYUSUTAN PERLENGKAPAN KANTOR (OE), 6313 BIAYA PENYUSUTAN KENDARAAN, 6314 BIAYA PENYUSUTAN BANGUNAN')
                            ->validationMessages([
                                'required' => 'COA beban penyusutan wajib dipilih'
                            ])
                            ->default(function (Get $get) {
                                $assetCoaId = $get('asset_coa_id');
                                if ($assetCoaId) {
                                    $assetCoa = ChartOfAccount::find($assetCoaId);
                                    if ($assetCoa) {
                                        $mapping = [
                                            '1210.01' => '6311',
                                            '1210.02' => '6312',
                                            '1210.03' => '6313',
                                            '1210.04' => '6314',
                                        ];

                                        if (isset($mapping[$assetCoa->code])) {
                                            $expenseCoa = ChartOfAccount::where('code', $mapping[$assetCoa->code])->first();
                                            return $expenseCoa?->id;
                                        }
                                    }
                                }
                                return null;
                            }),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Perhitungan Penyusutan')
                    ->schema([
                        Forms\Components\Placeholder::make('depreciable_amount')
                            ->label('Nilai yang Dapat Disusutkan')
                            ->content(function (Get $get) {
                                $purchaseCost = (float) str_replace(',', '', $get('purchase_cost') ?? 0);
                                $salvageValue = (float) str_replace(',', '', $get('salvage_value') ?? 0);
                                $depreciable = $purchaseCost - $salvageValue;
                                return 'Rp ' . number_format($depreciable, 2, ',', '.');
                            }),

                        Forms\Components\Placeholder::make('depreciation_method_explanation')
                            ->label('Metode Penyusutan')
                            ->content(function (Get $get) {
                                $method = $get('depreciation_method') ?? 'straight_line';
                                $usefulLife = $get('useful_life_years') ?? 1;

                                return match ($method) {
                                    'straight_line' => 'Garis Lurus: (Biaya aset - nilai sisa) ÷ umur manfaat',
                                    'declining_balance' => 'Saldo Menurun Ganda: 2 × (' . number_format((1 / $usefulLife) * 100, 1) . '%) × nilai buku awal',
                                    'sum_of_years_digits' => 'Jumlah Digit Tahun: (Biaya disusutkan) × (sisa masa manfaat ÷ ' . (($usefulLife * ($usefulLife + 1)) / 2) . ')',
                                    'units_of_production' => 'Unit Produksi: Akan diimplementasi',
                                    default => 'Garis Lurus: (Biaya aset - nilai sisa) ÷ umur manfaat'
                                };
                            }),

                        Forms\Components\Placeholder::make('annual_depreciation_display')
                            ->label('Penyusutan Per Tahun')
                            ->content(function (Get $get) {
                                $annual = (float) str_replace(',', '', $get('annual_depreciation') ?? 0);
                                return 'Rp ' . number_format($annual, 2, ',', '.');
                            }),

                        Forms\Components\Placeholder::make('monthly_depreciation_display')
                            ->label('Penyusutan Per Bulan')
                            ->content(function (Get $get) {
                                $monthly = (float) str_replace(',', '', $get('monthly_depreciation') ?? 0);
                                return 'Rp ' . number_format($monthly, 2, ',', '.');
                            }),

                        Forms\Components\Hidden::make('annual_depreciation'),
                        Forms\Components\Hidden::make('monthly_depreciation'),
                        Forms\Components\Hidden::make('book_value'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Status & Catatan')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Aktif',
                                'disposed' => 'Dijual/Dihapus',
                                'fully_depreciated' => 'Sudah Disusutkan Penuh',
                            ])
                            ->default('active')
                            ->required()
                            ->validationMessages([
                                'required' => 'Status aset wajib dipilih'
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                    ])
                    ->columns(2),
            ]);
    }

    protected static function calculateDepreciation(Get $get, Set $set): void
    {
        $purchaseCost = (float) str_replace(',', '', $get('purchase_cost') ?? 0);
        $salvageValue = (float) str_replace(',', '', $get('salvage_value') ?? 0);
        $usefulLife = (float) $get('useful_life_years') ?? 1;
        $depreciationMethod = $get('depreciation_method') ?? 'straight_line';

        if ($purchaseCost > 0 && $usefulLife > 0) {
            $depreciableAmount = $purchaseCost - $salvageValue;
            $annualDepreciation = 0;
            $monthlyDepreciation = 0;

            switch ($depreciationMethod) {
                case 'straight_line':
                    // Metode Garis Lurus: (Biaya aset - nilai sisa) ÷ umur manfaat
                    $annualDepreciation = $depreciableAmount / $usefulLife;
                    break;

                case 'declining_balance':
                    // Metode Saldo Menurun Ganda: 2 × (1 ÷ masa manfaat) × nilai buku awal
                    $depreciationRate = (1 / $usefulLife) * 2; // 2x tarif garis lurus
                    $annualDepreciation = $purchaseCost * $depreciationRate;

                    // Pastikan tidak melebihi nilai yang dapat disusutkan
                    $maxDepreciable = $purchaseCost - $salvageValue;
                    $annualDepreciation = min($annualDepreciation, $maxDepreciable);
                    break;

                case 'sum_of_years_digits':
                    // Metode Jumlah Digit Tahun: (Biaya disusutkan) × (sisa masa manfaat ÷ jumlah digit tahun)
                    // Untuk form, hitung untuk tahun pertama (sisa masa manfaat = umur manfaat)
                    $sumOfYears = ($usefulLife * ($usefulLife + 1)) / 2; // n(n+1)/2
                    $remainingYears = $usefulLife; // Tahun pertama
                    $annualDepreciation = $depreciableAmount * ($remainingYears / $sumOfYears);
                    break;

                case 'units_of_production':
                    // Metode Unit Produksi: akan diimplementasi nanti
                    $annualDepreciation = $depreciableAmount / $usefulLife; // placeholder
                    break;

                default:
                    $annualDepreciation = $depreciableAmount / $usefulLife;
                    break;
            }

            $monthlyDepreciation = $annualDepreciation / 12;

            $set('annual_depreciation', number_format($annualDepreciation, 2, '.', ''));
            $set('monthly_depreciation', number_format($monthlyDepreciation, 2, '.', ''));
            $set('book_value', number_format($purchaseCost, 2, '.', ''));
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Calculate salvage value if all required fields are present
        if (
            isset($data['product_id']) && isset($data['depreciation_method']) &&
            isset($data['purchase_cost']) && isset($data['useful_life_years'])
        ) {
            $purchaseCost = (float) str_replace(',', '', $data['purchase_cost'] ?? 0);
            if ($purchaseCost > 0) {
                $data['salvage_value'] = number_format($purchaseCost * 0.05, 2, '.', '');
            }
        }

        // Ensure COA relationships are properly loaded for edit form
        if (isset($data['asset_coa_id'])) {
            $assetCoa = ChartOfAccount::find($data['asset_coa_id']);
            if ($assetCoa) {
                $mapping = [
                    '1210.01' => ['accumulated' => '1220.01', 'expense' => '6311'],
                    '1210.02' => ['accumulated' => '1220.02', 'expense' => '6312'],
                    '1210.03' => ['accumulated' => '1220.03', 'expense' => '6313'],
                    '1210.04' => ['accumulated' => '1220.04', 'expense' => '6314'],
                ];

                if (isset($mapping[$assetCoa->code])) {
                    // Only set if not already set
                    if (!isset($data['accumulated_depreciation_coa_id']) || !$data['accumulated_depreciation_coa_id']) {
                        $accumulatedCoa = ChartOfAccount::where('code', $mapping[$assetCoa->code]['accumulated'])->first();
                        if ($accumulatedCoa) {
                            $data['accumulated_depreciation_coa_id'] = $accumulatedCoa->id;
                        }
                    }

                    if (!isset($data['depreciation_expense_coa_id']) || !$data['depreciation_expense_coa_id']) {
                        $expenseCoa = ChartOfAccount::where('code', $mapping[$assetCoa->code]['expense'])->first();
                        if ($expenseCoa) {
                            $data['depreciation_expense_coa_id'] = $expenseCoa->id;
                        }
                    }
                }
            }
        }

        return $data;
    }

    protected static function calculateSalvageValue(Get $get, Set $set): void
    {
        $productId = $get('product_id');
        $depreciationMethod = $get('depreciation_method');
        $purchaseCost = (float) str_replace(',', '', $get('purchase_cost') ?? 0);
        $usefulLifeYears = (float) $get('useful_life_years') ?? 0;

        // Only calculate salvage value if all required fields are filled
        if ($productId && $depreciationMethod && $purchaseCost > 0 && $usefulLifeYears > 0) {
            $salvageValue = $purchaseCost * 0.05; // 5% of purchase cost
            $set('salvage_value', number_format($salvageValue, 2, '.', ''));
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Asset')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn($record) => $record->cabang ? "{$record->cabang->kode} - {$record->cabang->nama}" : '-')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('assetCoa.name')
                    ->label('Kategori Aset')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Tgl Beli')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('usage_date')
                    ->label('Tgl Pakai')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_cost')
                    ->label('Biaya Aset')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('useful_life_years')
                    ->label('Umur (Thn)')
                    ->suffix(' tahun')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('depreciation_method')
                    ->label('Metode')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'straight_line' => 'Garis Lurus',
                        'declining_balance' => 'Saldo Menurun Ganda',
                        'sum_of_years_digits' => 'Jumlah Digit Tahun',
                        'units_of_production' => 'Unit Produksi',
                        default => $state,
                    })
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('monthly_depreciation')
                    ->label('Penyusutan/Bulan')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('accumulated_depreciation')
                    ->label('Akum. Penyusutan')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('book_value')
                    ->label('Nilai Buku')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'disposed',
                        'warning' => 'fully_depreciated',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'disposed' => 'Dijual/Dihapus',
                        'fully_depreciated' => 'Disusutkan Penuh',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product Master')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('purchaseOrder.po_number')
                    ->label('Nomor PO')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'disposed' => 'Dijual/Dihapus',
                        'fully_depreciated' => 'Sudah Disusutkan Penuh',
                    ]),

                Tables\Filters\SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->options(function () {
                        $user = Auth::user();
                        $manageType = $user?->manage_type ?? [];

                        if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                            return \App\Models\Cabang::where('id', $user?->cabang_id)
                                ->get()
                                ->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                                });
                        }

                        return \App\Models\Cabang::all()->mapWithKeys(function ($cabang) {
                            return [$cabang->id => "{$cabang->kode} - {$cabang->nama}"];
                        });
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('asset_coa_id')
                    ->label('Kategori Aset')
                    ->relationship('assetCoa', 'name'),

                Tables\Filters\SelectFilter::make('depreciation_method')
                    ->label('Metode Penyusutan')
                    ->options([
                        'straight_line' => 'Garis Lurus',
                        'declining_balance' => 'Saldo Menurun Ganda',
                        'sum_of_years_digits' => 'Jumlah Digit Tahun',
                        'units_of_production' => 'Unit Produksi',
                    ]),

                Tables\Filters\Filter::make('purchase_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('purchase_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('purchase_date', '<=', $date),
                            );
                    }),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('purchaseOrder.supplier', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()->color('primary'),
                    Tables\Actions\EditAction::make()->color('success'),
                    Tables\Actions\Action::make('calculate_depreciation')
                        ->color('warning')
                        ->label('Hitung Penyusutan')
                        ->icon('heroicon-o-calculator')
                        ->action(function (Asset $record) {
                            $record->calculateDepreciation();
                            \Filament\Notifications\Notification::make()
                                ->title('Penyusutan berhasil dihitung')
                                ->success()
                                ->persistent()
                                ->sendToDatabase(\Filament\Facades\Filament::auth()->user());
                        }),
                    Tables\Actions\Action::make('post_asset_journal')
                        ->color('info')
                        ->label('Post Jurnal Akuisisi')
                        ->icon('heroicon-o-document-plus')
                        ->visible(fn(Asset $record) => !$record->hasPostedJournals())
                        ->action(function (Asset $record) {
                            $assetService = new \App\Services\AssetService();
                            $assetService->postAssetAcquisitionJournal($record);
                            \Filament\Notifications\Notification::make()
                                ->title('Jurnal akuisisi aset berhasil dipost')
                                ->success()
                                ->persistent()
                                ->sendToDatabase(\Filament\Facades\Filament::auth()->user());
                        }),
                    Tables\Actions\Action::make('post_depreciation_journal')
                        ->color('purple')
                        ->label('Post Jurnal Penyusutan')
                        ->icon('heroicon-o-chart-bar')
                        ->visible(fn(Asset $record) => $record->status !== 'fully_depreciated' && $record->monthly_depreciation > 0)
                        ->action(function (Asset $record) {
                            $currentMonth = now()->format('Y-m');
                            $depreciationAmount = $record->monthly_depreciation ?? 0;

                            if ($depreciationAmount <= 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Tidak ada penyusutan untuk dipost')
                                    ->body('Nilai penyusutan bulanan asset ini adalah 0 atau negatif. Pastikan asset belum fully depreciated dan nilai penyusutan sudah dihitung dengan benar.')
                                    ->warning()
                                    ->persistent()
                                    ->sendToDatabase(\Filament\Facades\Filament::auth()->user());
                                return;
                            }

                            // Check if depreciation journal already exists for this month
                            $existingDepreciation = \App\Models\JournalEntry::where('source_type', 'App\Models\Asset')
                                ->where('source_id', $record->id)
                                ->where('description', 'like', '%Depreciation expense%')
                                ->where('date', '>=', now()->startOfMonth())
                                ->where('date', '<=', now()->endOfMonth())
                                ->exists();

                            if ($existingDepreciation) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Jurnal penyusutan bulan ini sudah ada')
                                    ->body('Jurnal penyusutan untuk bulan ' . now()->format('F Y') . ' sudah pernah dipost.')
                                    ->warning()
                                    ->persistent()
                                    ->sendToDatabase(\Filament\Facades\Filament::auth()->user());
                                return;
                            }

                            $assetService = new \App\Services\AssetService();
                            $assetService->postAssetDepreciationJournal($record, $depreciationAmount, $currentMonth);
                            \Filament\Notifications\Notification::make()
                                ->title('Jurnal penyusutan berhasil dipost')
                                ->body('Jurnal penyusutan untuk bulan ' . now()->format('F Y') . ' telah berhasil dibuat.')
                                ->success()
                                ->persistent()
                                ->sendToDatabase(\Filament\Facades\Filament::auth()->user());
                        }),
                    Tables\Actions\Action::make('view_asset_journals')
                        ->color('gray')
                        ->label('Lihat Jurnal')
                        ->icon('heroicon-o-eye')
                        ->url(fn(Asset $record) => route('filament.admin.resources.assets.view', $record))
                        ->openUrlInNewTab(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ], position: ActionsPosition::BeforeCells)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->description(new HtmlString('
                <details class="space-y-2">
                    <summary class="cursor-pointer font-semibold text-gray-700 dark:text-gray-300">
                        📋 Panduan Penggunaan Asset Management
                    </summary>
                    <div class="mt-4 space-y-4 text-sm text-gray-600 dark:text-gray-400">
                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">🎯 Fungsi Utama</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li>Mengelola aset tetap perusahaan (mesin, kendaraan, bangunan, dll)</li>
                                <li>Menghitung penyusutan otomatis berdasarkan metode yang dipilih</li>
                                <li>Melacak nilai buku dan akumulasi penyusutan</li>
                                <li>Membuat jurnal akuntansi untuk akuisisi dan penyusutan</li>
                                <li>Mengintegrasikan dengan Purchase Order dan Quality Control</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">📊 Status Flow</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>Aktif:</strong> Aset sedang digunakan dan disusutkan</li>
                                <li><strong>Disusutkan Penuh:</strong> Aset telah mencapai umur ekonomis</li>
                                <li><strong>Dijual/Dihapus:</strong> Aset telah dijual atau dihapus dari inventaris</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">✅ Validasi & Aturan</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li>Nilai salvage otomatis dihitung (10% dari biaya aset)</li>
                                <li>Umur ekonomis minimal 1 tahun, maksimal 50 tahun</li>
                                <li>Biaya aset harus lebih besar dari nilai salvage</li>
                                <li>COA aset dan penyusutan harus sesuai mapping yang ditentukan</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">⚡ Aksi Tersedia</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>Hitung Penyusutan:</strong> Menghitung ulang nilai penyusutan bulanan</li>
                                <li><strong>Post Jurnal Akuisisi:</strong> Membuat jurnal untuk penerimaan aset</li>
                                <li><strong>Post Jurnal Penyusutan:</strong> Membuat jurnal penyusutan bulanan</li>
                                <li><strong>Lihat Jurnal:</strong> Melihat semua jurnal terkait aset</li>
                                <li><strong>Transfer Aset:</strong> Memindahkan aset antar cabang</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">🔐 Permission & Akses</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li>Admin dapat mengelola semua aset di semua cabang</li>
                                <li>User biasa hanya dapat melihat aset di cabang mereka</li>
                                <li>Manager dapat approve transfer aset antar cabang</li>
                                <li>Akses ke jurnal akuntansi berdasarkan permission accounting</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">🔗 Integrasi Sistem</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>Purchase Order:</strong> Aset dibuat dari PO yang disetujui</li>
                                <li><strong>Chart of Account:</strong> Mapping COA untuk jurnal otomatis</li>
                                <li><strong>General Ledger:</strong> Posting jurnal ke buku besar</li>
                                <li><strong>Asset Transfer:</strong> Sistem transfer aset antar cabang</li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">📈 Metode Penyusutan</h4>
                            <ul class="list-disc list-inside ml-4 mt-1 space-y-1">
                                <li><strong>Garis Lurus:</strong> Penyusutan merata per bulan</li>
                                <li><strong>Saldo Menurun Ganda:</strong> Penyusutan lebih besar di awal</li>
                                <li><strong>Jumlah Digit Tahun:</strong> Menggunakan faktor digit tahun</li>
                                <li><strong>Unit Produksi:</strong> Berdasarkan penggunaan aktual</li>
                            </ul>
                        </div>
                    </div>
                </details>
            '));
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Asset Information')
                ->schema([
                    TextEntry::make('code')->label('Asset Code'),
                    TextEntry::make('name')->label('Asset Name'),
                    TextEntry::make('cabang.nama')->label('Branch'),
                    TextEntry::make('purchase_date')->date('d/m/Y')->label('Purchase Date'),
                    TextEntry::make('usage_date')->date('d/m/Y')->label('Usage Date'),
                    TextEntry::make('purchase_cost')->money('IDR')->label('Purchase Cost'),
                    TextEntry::make('salvage_value')->money('IDR')->label('Salvage Value'),
                    TextEntry::make('useful_life_years')->label('Useful Life (Years)'),
                    TextEntry::make('depreciation_method')->label('Depreciation Method'),
                    TextEntry::make('status')->badge()->label('Status'),
                    TextEntry::make('notes')->label('Notes')->columnSpanFull(),
                ])->columns(2),
            InfolistSection::make('Chart of Accounts')
                ->schema([
                    TextEntry::make('assetCoa.code')->label('Asset COA'),
                    TextEntry::make('assetCoa.name')->label('Asset Account'),
                    TextEntry::make('accumulatedDepreciationCoa.code')->label('Accumulated Depreciation COA'),
                    TextEntry::make('accumulatedDepreciationCoa.name')->label('Accumulated Depreciation Account'),
                    TextEntry::make('depreciationExpenseCoa.code')->label('Depreciation Expense COA'),
                    TextEntry::make('depreciationExpenseCoa.name')->label('Depreciation Expense Account'),
                ])->columns(2),
            InfolistSection::make('Depreciation Information')
                ->schema([
                    TextEntry::make('annual_depreciation')->money('IDR')->label('Annual Depreciation'),
                    TextEntry::make('monthly_depreciation')->money('IDR')->label('Monthly Depreciation'),
                    TextEntry::make('accumulated_depreciation')->money('IDR')->label('Accumulated Depreciation'),
                    TextEntry::make('book_value')->money('IDR')->label('Book Value'),
                ])->columns(2),
            InfolistSection::make('Journal Entries')
                ->headerActions([
                    \Filament\Infolists\Components\Actions\Action::make('view_journal_entries')
                        ->label('View All Journal Entries')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->url(function ($record) {
                            // Redirect to JournalEntryResource with filter for this asset
                            $sourceType = urlencode(\App\Models\Asset::class);
                            $sourceId = $record->id;

                            return "/admin/journal-entries?tableFilters[source_type]={$sourceType}&tableFilters[source_id]={$sourceId}";
                        })
                        ->openUrlInNewTab()
                        ->visible(function ($record) {
                            return $record->journalEntries()->exists();
                        }),
                ])
                ->schema([
                    RepeatableEntry::make('journalEntries')
                        ->label('')
                        ->schema([
                            TextEntry::make('date')->date()->label('Date'),
                            TextEntry::make('coa.code')->label('COA'),
                            TextEntry::make('coa.name')->label('Account Name'),
                            TextEntry::make('debit')->money('IDR')->label('Debit')->color('success'),
                            TextEntry::make('credit')->money('IDR')->label('Credit')->color('danger'),
                            TextEntry::make('description')->label('Description'),
                            TextEntry::make('journal_type')->badge()->label('Type')->formatStateUsing(fn(string $state): string => match ($state) {
                                'asset_acquisition' => 'Asset Acquisition',
                                'asset_depreciation' => 'Asset Depreciation',
                                default => $state,
                            }),
                        ])
                        ->columns(7),
                ])
                ->columns(1)
                ->visible(function ($record) {
                    return $record->journalEntries()->exists();
                }),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DepreciationEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'view' => Pages\ViewAsset::route('/{record}'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}
