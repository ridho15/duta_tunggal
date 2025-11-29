<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillOfMaterialResource\Pages;
use App\Filament\Resources\BillOfMaterialResource\Pages\ViewBillOfMaterial;
use App\Http\Controllers\HelperController;
use App\Models\BillOfMaterial;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Services\BillOfMaterialService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;

class BillOfMaterialResource extends Resource
{
    protected static ?string $model = BillOfMaterial::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-pointing-in';

    protected static ?string $navigationGroup = 'Manufacturing Order';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form')
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode BOM')
                            ->validationMessages([
                                'required' => 'Kode BOM tidak boleh kosong',
                                'unique' => 'Kode BOM sudah digunakan !'
                            ])
                            ->reactive()
                            ->suffixAction(Action::make('generateCode')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate Kode Supplier')
                                ->action(function ($set, $get, $state) {
                                    $billOfMaterialService = app(BillOfMaterialService::class);
                                    $set('code', $billOfMaterialService->generateCode());
                                }))
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('nama_bom')
                            ->label('Nama BOM')
                            ->validationMessages([
                                'required' => 'Nama tidak boleh kosongs'
                            ])
                            ->required()
                            ->maxLength(255),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->required()
                            ->validationMessages([
                                'required' => 'Cabang belum dipilih',
                                'exists' => 'Cabang tidak ditemukan !'
                            ])->reactive()
                            ->relationship('cabang', 'nama')
                            ->searchable(['nama', 'kode'])
                            ->preload()
                            ->getOptionLabelFromRecordUsing(function (Cabang $cabang) {
                                return "({$cabang->kode}) {$cabang->nama}";
                            }),
                        Select::make('product_id')
                            ->required()
                            ->label('Product')
                            ->reactive()
                            ->preload()
                            ->searchable(['sku', 'name'])
                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                return "({$product->sku}) {$product->name}";
                            })
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $product = Product::find($state);
                                if ($product) {
                                    $set('uom_id', $product->uom_id);
                                    $listConversions = [];
                                    foreach ($product->unitConversions as $index => $conversion) {
                                        $listConversions[$index] = [
                                            'uom_id' => $conversion->uom_id,
                                            'nilai_konversi' => $conversion->nilai_konversi
                                        ];
                                    }

                                    $set('satuan_konversi', $listConversions);
                                }
                            })
                            ->validationMessages([
                                'required' => 'Produk belum di pilih',
                                'exists' => 'Produk tidak ditemukan !'
                            ])
                            ->relationship('product', 'name', function (Builder $query, $get) {
                                $query->where('is_manufacture', true)
                                    ->where('cabang_id', $get('cabang_id'));
                            }),
                        Select::make('uom_id')
                            ->label('Unif Of Measure (Satuan)')
                            ->preload()
                            ->reactive()
                            ->searchable(['name'])
                            ->relationship('uom', 'name')
                            ->getOptionLabelFromRecordUsing(function (UnitOfMeasure $uom) {
                                return "{$uom->name} ({$uom->abbreviation})";
                            })
                            ->validationMessages([
                                'required' => 'Unit of measure belum dipilih',
                                'exists' => 'Unit of measure tidak ditemukan !'
                            ])
                            ->required(),
                        TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(0.00),
                        Fieldset::make('Biaya Produksi')
                            ->schema([
                                TextInput::make('labor_cost')
                                    ->label('Biaya Tenaga Kerja Langsung (TKL)')
                                    ->helperText('Biaya tenaga kerja untuk memproduksi produk ini')
                                    ->numeric()
                                    ->indonesianMoney()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(fn ($set, $get) => self::updateTotalCost($set, $get)),
                                TextInput::make('overhead_cost')
                                    ->label('Biaya Overhead')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->indonesianMoney()
                                    ->afterStateUpdated(fn ($state, $set, $get) => self::updateTotalCost($set, $get)),
                                Placeholder::make('material_cost_display')
                                    ->label('Biaya Material')
                                    ->reactive()
                                    ->content(function ($get) {
                                        $items = $get('items') ?? [];
                                        $materialCost = 0;
                                        foreach ($items as $item) {
                                            $unitPrice = HelperController::parseIndonesianMoney($item['unit_price'] ?? 0);
                                            $quantity = (float) ($item['quantity'] ?? 0);
                                            $materialCost += ($unitPrice * $quantity);
                                        }
                                        return 'Rp ' . number_format($materialCost, 0, ',', '.');
                                    }),
                                Placeholder::make('total_cost_display')
                                    ->label('Total Biayas')
                                    ->reactive()
                                    ->content(function ($get) {
                                        $materialCost = 0;
                                        $items = $get('items') ?? [];
                                        foreach ($items as $item) {
                                            $unitPrice = HelperController::parseIndonesianMoney($item['unit_price'] ?? 0);
                                            $quantity = (float) ($item['quantity'] ?? 0);
                                            $materialCost += ($unitPrice * $quantity);
                                        }
                                        $laborCost = HelperController::parseIndonesianMoney($get('labor_cost'));
                                        $overheadCost = HelperController::parseIndonesianMoney($get('overhead_cost'));
                                        $totalCost = $materialCost + $laborCost + $overheadCost;
                                        return 'Rp ' . number_format($totalCost, 0, ',', '.');
                                    }),
                                Hidden::make('total_cost')
                                    ->dehydrated()
                                    ->mutateDehydratedStateUsing(function ($state, $get) {
                                        $materialCost = 0;
                                        $items = $get('items') ?? [];
                                        foreach ($items as $item) {
                                            $unitPrice = (float) ($item['unit_price'] ?? 0);
                                            $quantity = (float) ($item['quantity'] ?? 0);
                                            $materialCost += ($unitPrice * $quantity);
                                        }

                                        $laborCost = (float) $get('labor_cost');
                                        $overheadCost = (float) $get('overhead_cost');
                                        return $materialCost + $laborCost + $overheadCost;
                                    }),
                            ])
                            ->columns(2),
                        Fieldset::make('Pengaturan Akuntansi')
                            ->schema([
                                Select::make('finished_goods_coa_id')
                                    ->label('COA Persediaan Barang Jadi')
                                    ->helperText('COA untuk mencatat persediaan barang jadi setelah produksi selesai')
                                    ->relationship('finishedGoodsCoa', 'name')
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        return "({$record->code}) {$record->name}";
                                    })
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->nullable(),
                                Select::make('work_in_progress_coa_id')
                                    ->label('COA Persediaan Barang Dalam Proses')
                                    ->helperText('COA untuk mencatat persediaan barang dalam proses selama produksi')
                                    ->relationship('workInProgressCoa', 'name')
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        return "({$record->code}) {$record->name}";
                                    })
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->nullable(),
                            ])
                            ->columns(2),
                        Textarea::make('note')
                            ->label('Catatan')
                            ->nullable(),
                        Toggle::make('is_active')
                            ->required(),
                        Repeater::make('satuan_konversi')
                            ->columnSpanFull()
                            ->columns(2)
                            ->reactive()
                            ->disabled()
                            ->label("Satuan Konversi")
                            ->schema([
                                Select::make('uom_id')
                                    ->label('Satuan')
                                    ->preload()
                                    ->disabled()
                                    ->reactive()
                                    ->searchable()
                                    ->options(function () {
                                        return UnitOfMeasure::get()->pluck('name', 'id');
                                    }),
                                TextInput::make('nilai_konversi')
                                    ->label('Nilai Konversi')
                                    ->reactive()
                                    ->disabled()
                                    ->numeric(),
                            ]),
                        Repeater::make('items')
                            ->relationship()
                            ->columnSpanFull()
                            ->addAction(function (Action $action) {
                                return $action->color('primary')
                                    ->icon('heroicon-o-plus-circle');
                            })
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data) {
                                $listConversions = [];
                                $product = Product::find($data['product_id']);
                                foreach ($product->unitConversions as $index => $conversion) {
                                    $listConversions[$index] = [
                                        'uom_id' => $conversion->uom_id,
                                        'nilai_konversi' => $conversion->nilai_konversi
                                    ];
                                }
                                $data['satuan_konversi'] = $listConversions;

                                // Convert string values to numeric for proper calculations
                                if (isset($data['unit_price'])) {
                                    $data['unit_price'] = (float) $data['unit_price'];
                                }
                                if (isset($data['quantity'])) {
                                    $data['quantity'] = (float) $data['quantity'];
                                }
                                if (isset($data['subtotal'])) {
                                    $data['subtotal'] = (float) $data['subtotal'];
                                }
                                return $data;
                            })
                            ->columns(4)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Material')
                                    ->preload()
                                    ->reactive()
                                    ->searchable()
                                    ->validationMessages([
                                        'required' => 'Material belum dipilih',
                                        'exists' => 'Material tidak tersedia !'
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('uom_id', $product->uom_id);
                                            $set('unit_price', $product->cost_price);
                                            $listConversions = [];
                                            foreach ($product->unitConversions as $index => $conversion) {
                                                $listConversions[$index] = [
                                                    'uom_id' => $conversion->uom_id,
                                                    'nilai_konversi' => $conversion->nilai_konversi
                                                ];
                                            }

                                            $set('satuan_konversi', $listConversions);
                                            // Update subtotal and total cost after setting unit_price
                                            $quantity = (float) ($get('quantity') ?? 0);
                                            $set('subtotal', $product->cost_price * $quantity);
                                            self::updateTotalCost($set, $get);
                                        }
                                    })
                                    ->relationship('product', 'name', function (Builder $query) {
                                        $query->where('is_raw_material', true);
                                    })
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    })->required(),
                                Select::make('uom_id')
                                    ->label('Unif Of Measure (Satuan)')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('uom', 'name')
                                    ->required()
                                    ->reactive()
                                    ->getOptionLabelFromRecordUsing(function (UnitOfMeasure $uom) {
                                        return "{$uom->name} ({$uom->abbreviation})";
                                    })
                                    ->validationMessages([
                                        'required' => 'Satuan belum dipilih',
                                        'exists' => 'Satuan tidak ditemukan'
                                    ]),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Quantity tidak boleh kosong'
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get) {
                                        $unitPrice = (float) ($get('unit_price') ?? 0);
                                        $quantity = (float) ($get('quantity') ?? 0);
                                        $set('subtotal', $unitPrice * $quantity);
                                        self::updateTotalCost($set, $get);
                                    })
                                    ->default(0),
                                TextInput::make('unit_price')
                                    ->label('Harga per Satuan')
                                    ->numeric()
                                    ->indonesianMoney()
                                    ->disabled()
                                    ->dehydrated()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get) {
                                        $unitPrice = (float) ($get('unit_price') ?? 0);
                                        $quantity = (float) ($get('quantity') ?? 0);
                                        $set('subtotal', $unitPrice * $quantity);
                                        self::updateTotalCost($set, $get);
                                    }),
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->indonesianMoney()
                                    ->disabled()
                                    ->dehydrated()
                                    ->reactive(),
                                Textarea::make('note')
                                    ->label('Catatan')
                                    ->nullable(),
                                Repeater::make('satuan_konversi')
                                    ->label('Satuan Konversi')
                                    ->reactive()
                                    ->columnSpanFull()
                                    ->columns(2)
                                    ->schema([
                                        Select::make('uom_id')
                                            ->label('Satuan')
                                            ->preload()
                                            ->reactive()
                                            ->searchable()
                                            ->relationship('uom', 'name')
                                            ->required(),
                                        TextInput::make('nilai_konversi')
                                            ->label('Nilai Konversi')
                                            ->numeric()
                                            ->reactive()
                                            ->required(),
                                    ])
                            ])

                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode BOM')
                    ->searchable(),
                TextColumn::make('nama_bom')
                    ->label('Nama BOM')
                    ->searchable(),
                TextColumn::make('cabang')
                    ->label('Cabang')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('cabang', function ($query) use ($search) {
                            $query->where('kode', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->nama}";
                    }),
                TextColumn::make('product')
                    ->label('Product')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('product', function (Builder $query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%');
                        });
                    })
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    }),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uom.name')
                    ->label('Unit of measure (Satuan)')
                    ->sortable(),
                TextColumn::make('labor_cost')
                    ->label('Biaya TKL')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('overhead_cost')
                    ->label('Biaya BOP')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('items')
                    ->label('Biaya Material')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->formatStateUsing(function ($state, $record) {
                        $materialCost = 0;
                        foreach ($record->items as $item) {
                            $materialCost += $item->subtotal;
                        }
                        return 'Rp ' . number_format($materialCost, 2, ',', '.');
                    }),
                TextColumn::make('total_cost')
                    ->label('Total Biaya Produksi')
                    ->money('IDR')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        return 'Rp ' . number_format($state, 2, ',', '.');
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('finishedGoodsCoa.code')
                    ->label('COA Barang Jadi')
                    ->formatStateUsing(function ($state, $record) {
                        return $state ? "({$state}) {$record->finishedGoodsCoa->name}" : '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('workInProgressCoa.code')
                    ->label('COA Barang Dalam Proses')
                    ->formatStateUsing(function ($state, $record) {
                        return $state ? "({$state}) {$record->workInProgressCoa->name}" : '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('items')
                    ->label("Material")
                    ->formatStateUsing(function ($state, $record) {
                        return $state->product->sku . ' - ' . $state->product->name;
                    })
                    ->badge(),
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
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabang', 'nama')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(function (Cabang $cabang) {
                        return "({$cabang->kode}) {$cabang->nama}";
                    }),
                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                        return "({$product->sku}) {$product->name}";
                    }),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
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

    protected static function updateTotalCost(callable $set, callable $get): void
    {
        $materialCost = 0;
        $items = $get('items') ?? [];
        foreach ($items as $item) {
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            $materialCost += ($unitPrice * $quantity);
        }

        $laborCost = (float) $get('labor_cost');
        $overheadCost = (float) $get('overhead_cost');
        $totalCost = $materialCost + $laborCost + $overheadCost;

        $set('total_cost', $totalCost);
    }

    protected function updateSubtotal(callable $set, callable $get): void
    {
        $items = $get('items') ?? [];
        foreach ($items as $index => $item) {
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            $subtotal = $unitPrice * $quantity;
            $items[$index]['subtotal'] = $subtotal;
        }
        $set('items', $items);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\BillOfMaterialResource\RelationManagers\ProductionPlansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillOfMaterials::route('/'),
            'create' => Pages\CreateBillOfMaterial::route('/create'),
            'view' => ViewBillOfMaterial::route('/{record}'),
            'edit' => Pages\EditBillOfMaterial::route('/{record}/edit'),
        ];
    }
}
