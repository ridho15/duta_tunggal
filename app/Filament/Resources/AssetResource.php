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
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\RawJs;
use Filament\Tables\Enums\ActionsPosition;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    
    protected static ?string $navigationGroup = 'Finance - Akuntansi';
    
    protected static ?string $navigationLabel = 'Aset Tetap';
    
    protected static ?string $modelLabel = 'Aset Tetap';
    
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Aset')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Barang')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Tanggal Beli')
                            ->required()
                            ->default(now())
                            ->native(false),
                        
                        Forms\Components\DatePicker::make('usage_date')
                            ->label('Tanggal Pakai')
                            ->required()
                            ->default(now())
                            ->native(false),
                        
                        Forms\Components\Select::make('product_id')
                            ->label('Product Master')
                            ->relationship('product', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->sku . ' - ' . $record->name)
                            ->getSearchResultsUsing(fn (string $search) => \App\Models\Product::where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->get()
                                ->mapWithKeys(fn ($product) => [$product->id => $product->sku . ' - ' . $product->name])
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Pilih produk master yang akan dijadikan asset. Produk ini akan dilink dengan purchase order item.')
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
                            }),
                        
                        Forms\Components\TextInput::make('purchase_cost')
                            ->label('Biaya Aset (Rp)')
                            ->required()
                            ->numeric()
                            ->indonesianMoney()
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->helperText('Biaya perolehan aset = harga pembelian + biaya pengiriman + biaya instalasi + biaya lainnya. Akan diisi otomatis dari Purchase Order jika tersedia.')
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                static::calculateDepreciation($get, $set);
                            }),
                        
                        Forms\Components\TextInput::make('salvage_value')
                            ->label('Nilai Sisa (Rp)')
                            ->numeric()
                            ->indonesianMoney()
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->default(0)
                            ->helperText('Nilai sisa adalah estimasi nilai jual aset pada akhir umur manfaatnya. Biasanya 0-10% dari biaya perolehan, tergantung jenis aset.')
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                static::calculateDepreciation($get, $set);
                            }),
                        
                        Forms\Components\TextInput::make('useful_life_years')
                            ->label('Umur Manfaat Aset (Tahun)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(5)
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                static::calculateDepreciation($get, $set);
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
                                    '1210.01', '1210.02', '1210.03', '1210.04'
                                ])->get()->mapWithKeys(fn ($coa) => [$coa->id => $coa->code . ' - ' . $coa->name])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Pilih COA untuk Aset: 1210.01 PERALATAN KANTOR (OE), 1210.02 PERLENGKAPAN KANTOR (FF), 1210.03 KENDARAAN, 1210.04 BANGUNAN')
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
                                    '1220.01', '1220.02', '1220.03', '1220.04'
                                ])->get()->mapWithKeys(fn ($coa) => [$coa->id => $coa->code . ' - ' . $coa->name])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Pilih COA untuk Akumulasi Penyusutan: 1220.01 AKUMULASI BIAYA PENYUSUTAN PERALATAN KANTOR (OE), 1220.02 AKUMULASI BIAYA PENYUSUTAN PERLENGKAPAN KANTOR (FF), 1220.03 AKUMULASI BIAYA PENYUSUTAN KENDARAAN, 1220.04 AKUMULASI BIAYA PENYUSUTAN BANGUNAN')
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
                                    '6311', '6312', '6313', '6314'
                                ])->get()->mapWithKeys(fn ($coa) => [$coa->id => $coa->code . ' - ' . $coa->name])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Pilih COA untuk Beban Penyusutan: 6311 BIAYA PENYUSUTAN PERALATAN KANTOR (OE), 6312 BIAYA PENYUSUTAN PERLENGKAPAN KANTOR (OE), 6313 BIAYA PENYUSUTAN KENDARAAN, 6314 BIAYA PENYUSUTAN BANGUNAN')
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
                                
                                return match($method) {
                                    'straight_line' => 'Garis Lurus: (Biaya aset - nilai sisa) ÷ umur manfaat',
                                    'declining_balance' => 'Saldo Menurun Ganda: 2 × (' . number_format((1/$usefulLife)*100, 1) . '%) × nilai buku awal',
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
                            ->required(),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Purchase Order & Quality Control')
                    ->schema([
                        Forms\Components\Placeholder::make('po_number')
                            ->label('Nomor PO')
                            ->content(fn ($record) => $record?->purchaseOrder?->po_number ?? '-'),
                        
                        Forms\Components\Placeholder::make('po_date')
                            ->label('Tanggal PO')
                            ->content(fn ($record) => $record?->purchaseOrder?->order_date ? $record->purchaseOrder->order_date->format('d/m/Y') : '-'),
                        
                        Forms\Components\Placeholder::make('supplier_name')
                            ->label('Supplier')
                            ->content(fn ($record) => $record?->purchaseOrder?->supplier?->name ?? '-'),
                        
                        Forms\Components\Placeholder::make('qc_status_display')
                            ->label('Status QC')
                            ->content(fn ($record) => $record?->qc_status ?? 'Tidak ada data QC'),
                        
                        Forms\Components\Placeholder::make('qc_number')
                            ->label('Nomor QC')
                            ->content(fn ($record) => $record?->quality_control?->qc_number ?? '-'),
                        
                        Forms\Components\Placeholder::make('qc_passed_quantity')
                            ->label('Jumlah Lulus QC')
                            ->content(fn ($record) => $record?->quality_control ? $record->quality_control->passed_quantity . ' unit' : '-'),
                        
                        Forms\Components\Placeholder::make('qc_rejected_quantity')
                            ->label('Jumlah Reject QC')
                            ->content(fn ($record) => $record?->quality_control ? $record->quality_control->rejected_quantity . ' unit' : '-'),
                        
                        Forms\Components\Placeholder::make('qc_notes')
                            ->label('Catatan QC')
                            ->content(fn ($record) => $record?->quality_control?->notes ?? '-'),
                        
                        Forms\Components\Placeholder::make('qc_inspected_by')
                            ->label('Diperiksa Oleh')
                            ->content(fn ($record) => $record?->quality_control?->inspectedBy?->name ?? '-'),
                    ])
                    ->columns(3)
                    ->visibleOn('view'),
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                
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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'disposed' => 'Dijual/Dihapus',
                        'fully_depreciated' => 'Disusutkan Penuh',
                        default => $state,
                    }),
                
                Tables\Columns\BadgeColumn::make('qc_status')
                    ->label('Status QC')
                    ->colors([
                        'success' => 'Sudah diproses',
                        'warning' => 'Belum diproses',
                        'gray' => 'Tidak ada QC',
                    ])
                    ->formatStateUsing(fn (string $state): string => $state),
                
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
                
                Tables\Filters\SelectFilter::make('qc_status')
                    ->label('Status Quality Control')
                    ->options([
                        'Sudah diproses' => 'Sudah diproses',
                        'Belum diproses' => 'Belum diproses',
                        'Tidak ada QC' => 'Tidak ada QC',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            function (Builder $query, $status) {
                                switch ($status) {
                                    case 'Sudah diproses':
                                        return $query->whereHas('purchaseOrderItem.qualityControl', function ($q) {
                                            $q->whereIn('status', [1, true]);
                                        });
                                    case 'Belum diproses':
                                        return $query->whereHas('purchaseOrderItem.qualityControl', function ($q) {
                                            $q->whereIn('status', [0, false]);
                                        });
                                    case 'Tidak ada QC':
                                        return $query->whereDoesntHave('purchaseOrderItem.qualityControl');
                                    default:
                                        return $query;
                                }
                            }
                        );
                    }),
                
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
                                fn (Builder $query, $date): Builder => $query->whereDate('purchase_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('purchase_date', '<=', $date),
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
                                ->send();
                        }),
                    Tables\Actions\Action::make('post_asset_journal')
                        ->color('info')
                        ->label('Post Jurnal Akuisisi')
                        ->icon('heroicon-o-document-plus')
                        ->visible(fn (Asset $record) => !$record->hasPostedJournals())
                        ->action(function (Asset $record) {
                            $assetService = new \App\Services\AssetService();
                            $assetService->postAssetAcquisitionJournal($record);
                            \Filament\Notifications\Notification::make()
                                ->title('Jurnal akuisisi aset berhasil dipost')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('post_depreciation_journal')
                        ->color('purple')
                        ->label('Post Jurnal Penyusutan')
                        ->icon('heroicon-o-chart-bar')
                        ->action(function (Asset $record) {
                            $currentMonth = now()->format('Y-m');
                            $depreciationAmount = $record->monthlyDepreciation ?? 0;
                            
                            if ($depreciationAmount <= 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Tidak ada penyusutan untuk dipost')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            
                            $assetService = new \App\Services\AssetService();
                            $assetService->postAssetDepreciationJournal($record, $depreciationAmount, $currentMonth);
                            \Filament\Notifications\Notification::make()
                                ->title('Jurnal penyusutan berhasil dipost')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('view_asset_journals')
                        ->color('gray')
                        ->label('Lihat Jurnal')
                        ->icon('heroicon-o-eye')
                        ->url(fn (Asset $record) => route('filament.admin.resources.assets.view', $record))
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
            ->defaultSort('created_at', 'desc');
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
