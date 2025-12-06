<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialIssueResource\Pages;
use App\Filament\Resources\MaterialIssueResource\RelationManagers;
use App\Http\Controllers\HelperController;
use App\Models\InventoryStock;
use App\Models\MaterialIssue;
use App\Models\Product;
use App\Services\ManufacturingService;
use App\Services\ManufacturingJournalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MaterialIssueResource extends Resource
{
    protected static ?string $model = MaterialIssue::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Manufacturing Order';

    protected static ?string $navigationLabel = 'Pengambilan Bahan Baku';

    protected static ?string $modelLabel = 'Pengambilan Bahan Baku';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Informasi Dasar')
                    ->schema([
                        Forms\Components\Hidden::make('manufacturing_order_id'),
                        Forms\Components\TextInput::make('issue_number')
                            ->label('Nomor Issue')
                            ->required()
                            ->maxLength(255)
                            ->default(function () {
                                $service = app(ManufacturingService::class);
                                return $service->generateIssueNumber('issue');
                            })
                            ->rules(function ($context, $record) {
                                return [
                                    'required',
                                    'string',
                                    'max:255',
                                    \Illuminate\Validation\Rule::unique('material_issues', 'issue_number')->ignore($record->id ?? null)
                                ];
                            })
                            ->validationMessages([
                                'required' => 'Nomor Issue wajib diisi.',
                                'string' => 'Nomor Issue harus berupa teks.',
                                'max' => 'Nomor Issue maksimal 255 karakter.',
                                'unique' => 'Nomor Issue sudah digunakan.',
                            ])
                            ->suffixAction(
                                FormAction::make('generate')
                                    ->icon('heroicon-m-arrow-path')
                                    ->action(function ($set, $get) {
                                        $service = app(ManufacturingService::class);
                                        $type = $get('type') ?? 'issue';
                                        $set('issue_number', $service->generateIssueNumber($type));
                                    })
                            ),
                        Forms\Components\Select::make('type')
                            ->label('Tipe')
                            ->options([
                                'issue' => 'Ambil Barang',
                                'return' => 'Retur Barang',
                            ])
                            ->required()
                            ->default('issue')
                            ->rules(['required', 'in:issue,return'])
                            ->validationMessages([
                                'required' => 'Tipe wajib dipilih.',
                                'in' => 'Tipe harus salah satu dari: Ambil Barang atau Retur Barang.',
                            ])
                            ->reactive()
                            ->afterStateUpdated(function ($set, $state) {
                                $service = app(ManufacturingService::class);
                                $set('issue_number', $service->generateIssueNumber($state));
                            }),
                        Forms\Components\Select::make('production_plan_id')
                            ->label('Rencana Produksi')
                            ->relationship('productionPlan', 'plan_number', fn(Builder $query) => $query->where('status', 'scheduled'))
                            ->searchable()
                            ->preload()
                            ->required(fn($get) => $get('type') === 'issue')
                            ->rules(['required_if:type,issue', 'exists:production_plans,id'])
                            ->validationMessages([
                                'required_if' => 'Rencana Produksi wajib dipilih untuk tipe Ambil Barang.',
                                'exists' => 'Rencana Produksi yang dipilih tidak valid.',
                            ])
                            ->visible(fn($get) => $get('type') === 'issue')
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                if ($state && $get('type') === 'issue') {
                                    // Auto-load materials from Production Plan's BOM
                                    $productionPlan = \App\Models\ProductionPlan::with(['billOfMaterial.items.product'])->find($state);
                                    if ($productionPlan && $productionPlan->billOfMaterial) {
                                        $items = [];
                                        foreach ($productionPlan->billOfMaterial->items as $bomItem) {
                                            $items[] = [
                                                'product_id' => $bomItem->product_id,
                                                'uom_id' => $bomItem->uom_id,
                                                'quantity' => $bomItem->quantity * $productionPlan->quantity,
                                                'cost_per_unit' => $bomItem->product->cost_price ?? 0,
                                                'total_cost' => ($bomItem->quantity * $productionPlan->quantity) * ($bomItem->product->cost_price ?? 0),
                                                'warehouse_id' => $productionPlan->warehouse_id, // Auto-fill from ProductionPlan
                                                'rak_id' => null,
                                                'notes' => null,
                                            ];
                                        }
                                        $set('items', $items);
                                        // Auto-fill warehouse from ProductionPlan
                                        $set('warehouse_id', $productionPlan->warehouse_id);
                                        // Prefer existing MO for this Production Plan if available
                                        $mo = \App\Models\ManufacturingOrder::where('production_plan_id', $productionPlan->id)->latest('id')->first();
                                        if ($mo) {
                                            $set('manufacturing_order_id', $mo->id);
                                        }
                                    }
                                }
                            })
                            ->getOptionLabelFromRecordUsing(function (\App\Models\ProductionPlan $record) {
                                return $record->plan_number . ' - ' . $record->name . ' (' . $record->product->name . ' - ' . $record->quantity . ' ' . $record->uom->name . ')';
                            }),
                        Forms\Components\Select::make('warehouse_id')
                            ->label('Gudang')
                            ->options(function () {
                                $user = Auth::user();
                                $manageType = $user?->manage_type ?? [];
                                $query = \App\Models\Warehouse::where('status', true);
                                
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
                                $query = \App\Models\Warehouse::where('status', true)
                                    ->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")
                                          ->orWhere('kode', 'like', "%{$search}%");
                                    });
                                
                                if (!$user || !is_array($manageType) || !in_array('all', $manageType)) {
                                    $query->where('cabang_id', $user?->cabang_id);
                                }
                                
                                return $query->limit(50)->get()->mapWithKeys(function ($warehouse) {
                                    return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}"];
                                });
                            })
                            ->required()
                            ->nullable()
                            ->rules(['required', 'exists:warehouses,id'])
                            ->validationMessages([
                                'required' => 'Gudang wajib dipilih.',
                                'exists' => 'Gudang yang dipilih tidak valid.',
                            ])
                            ->getOptionLabelFromRecordUsing(
                                fn(\App\Models\Warehouse $record): string =>
                                $record->kode . ' - ' . $record->name
                            ),
                        Forms\Components\DatePicker::make('issue_date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now())
                            ->rules(['required', 'date', 'before_or_equal:today'])
                            ->validationMessages([
                                'required' => 'Tanggal wajib diisi.',
                                'date' => 'Tanggal harus berupa format tanggal yang valid.',
                                'before_or_equal' => 'Tanggal tidak boleh melebihi hari ini.',
                            ])
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'pending_approval' => 'Menunggu Persetujuan',
                                'approved' => 'Disetujui',
                                'completed' => 'Selesai',
                            ])
                            ->required()
                            ->default('draft')
                            ->visible(fn($context) => $context !== 'create')
                            ->disabled(fn($context) => $context === 'edit'), // Prevent direct status change in edit form
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Detail Bahan')
                    ->description('Bahan baku akan otomatis terisi berdasarkan Formula Produksi yang dipilih. Anda dapat menyesuaikan jumlah yang diambil (misalnya ambil 50% saja dari kebutuhan).')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->label('Items')
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data) {
                                // Ensure numeric values are stored correctly
                                if (isset($data['quantity'])) {
                                    $data['quantity'] = (float) $data['quantity'];
                                }
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeFillUsing(function ($data) {
                                if ($data['product_id'] && $data['warehouse_id']) {
                                    $inventoryStock = InventoryStock::where('product_id', $data['product_id'])
                                        ->where('warehouse_id', $data['warehouse_id'])
                                        ->first();
                                    if ($inventoryStock) {
                                        $data['available_stock_display'] = $inventoryStock->qty_available;
                                    } else {
                                        $data['available_stock_display'] = 0;
                                    }
                                }
                                return $data;
                            })
                            ->rules(['required', 'array', 'min:1'])
                            ->validationMessages([
                                'required' => 'Minimal harus ada 1 item bahan baku.',
                                'array' => 'Items harus berupa array.',
                                'min' => 'Minimal harus ada 1 item bahan baku.',
                            ])
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Produk (Bahan Baku)')
                                    ->relationship('product', 'name', function (Builder $query) {
                                        return $query->where('is_raw_material', true);
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->rules(['required', 'exists:products,id'])
                                    ->validationMessages([
                                        'required' => 'Produk wajib dipilih.',
                                        'exists' => 'Produk yang dipilih tidak valid.',
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('uom_id', $product->uom_id);
                                            $set('cost_per_unit', $product->cost_price);
                                        }
                                        $set('warehouse_id', null); // Reset warehouse when product changes
                                        // Reset available stock display when product changes
                                        $set('available_stock_display', '0.00');
                                    })
                                    ->getOptionLabelFromRecordUsing(
                                        fn(Product $record) =>
                                        "({$record->sku}) {$record->name}"
                                    ),
                                Forms\Components\Select::make('uom_id')
                                    ->label('Satuan')
                                    ->relationship('uom', 'name')
                                    ->required()
                                    ->rules(['required', 'exists:unit_of_measures,id'])
                                    ->validationMessages([
                                        'required' => 'Satuan wajib dipilih.',
                                        'exists' => 'Satuan yang dipilih tidak valid.',
                                    ])
                                    ->preload(),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required()
                                    ->rules(['required', 'numeric', 'min:0.01'])
                                    ->validationMessages([
                                        'required' => 'Quantity wajib diisi.',
                                        'numeric' => 'Quantity harus berupa angka.',
                                        'min' => 'Quantity minimal 0.01.',
                                    ])
                                    ->readOnly()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get) {
                                        $qty = (float) $get('quantity');
                                        $cost = HelperController::parseIndonesianMoney($get('cost_per_unit') ?? '0');
                                        $set('total_cost', $qty * $cost);
                                    }),
                                Forms\Components\TextInput::make('cost_per_unit')
                                    ->label('Cost Price')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->indonesianMoney()
                                    ->rules(['required', 'numeric', 'min:0'])
                                    ->validationMessages([
                                        'required' => 'Cost Price wajib diisi.',
                                        'numeric' => 'Cost Price harus berupa angka.',
                                        'min' => 'Cost Price minimal 0.',
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get) {
                                        $qty = (float) $get('quantity');
                                        $cost = HelperController::parseIndonesianMoney($get('cost_per_unit') ?? '0');
                                        $set('total_cost', $qty * $cost);
                                    }),
                                Forms\Components\TextInput::make('total_cost')
                                    ->label('Subtotal')
                                    ->disabled()
                                    ->indonesianMoney()
                                    ->dehydrated()
                                    ->rules(['numeric', 'min:0'])
                                    ->validationMessages([
                                        'numeric' => 'Subtotal harus berupa angka.',
                                        'min' => 'Subtotal minimal 0.',
                                    ]),
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->relationship('warehouse', 'name')
                                    ->searchable('kode', 'name')
                                    ->reactive()
                                    ->nullable()
                                    ->required()
                                    ->rules(['required', 'exists:warehouses,id'])
                                    ->validationMessages([
                                        'required' => 'Gudang wajib dipilih.',
                                        'exists' => 'Gudang yang dipilih tidak valid.',
                                    ])
                                    ->default(null)
                                    ->options(function (callable $get) {
                                        $productId = $get('product_id');
                                        if ($productId) {
                                            return \App\Models\Warehouse::whereHas('inventoryStock', function ($q) use ($productId) {
                                                $q->where('product_id', $productId)->whereRaw('qty_available > 0');
                                            })
                                                ->orderBy('kode')
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(function ($warehouse) {
                                                    return [$warehouse->id => $warehouse->kode . ' - ' . $warehouse->name];
                                                })
                                                ->toArray();
                                        }
                                        return [];
                                    })
                                    ->getOptionLabelFromRecordUsing(
                                        fn(\App\Models\Warehouse $record): string =>
                                        $record->kode . ' - ' . $record->name
                                    )
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $productId = $get('product_id');
                                        $warehouseId = $state;
                                        if ($productId && $warehouseId) {
                                            $stock = \App\Models\InventoryStock::where('product_id', $productId)
                                                ->where('warehouse_id', $warehouseId)
                                                ->first();
                                            $available = $stock ? $stock->qty_available : 0;
                                            $set('available_stock_display', number_format($available, 2));
                                        } else {
                                            $set('available_stock_display', '0.00');
                                        }
                                    }),
                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->relationship('rak', 'name')
                                    ->searchable()
                                    ->nullable()
                                    ->default(null)
                                    ->options(function (callable $get) {
                                        $productId = $get('product_id');
                                        $warehouseId = $get('warehouse_id');

                                        if ($productId && $warehouseId) {
                                            // Get racks that have inventory stock for this product in this warehouse
                                            return \App\Models\Rak::whereHas('inventoryStock', function ($q) use ($productId, $warehouseId) {
                                                $q->where('product_id', $productId)
                                                    ->where('warehouse_id', $warehouseId)
                                                    ->whereRaw('qty_available - qty_reserved > 0');
                                            })
                                                ->orderBy('name')
                                                ->get()
                                                ->mapWithKeys(function ($rak) {
                                                    return [$rak->id => $rak->name . ' (' . $rak->code . ')'];
                                                })
                                                ->toArray();
                                        }

                                        if ($warehouseId) {
                                            // If no product selected, show all racks in the warehouse
                                            return \App\Models\Rak::where('warehouse_id', $warehouseId)
                                                ->orderBy('name')
                                                ->get()
                                                ->mapWithKeys(function ($rak) {
                                                    return [$rak->id => $rak->name . ' (' . $rak->code . ')'];
                                                })
                                                ->toArray();
                                        }

                                        return [];
                                    })
                                    ->getOptionLabelFromRecordUsing(
                                        fn(\App\Models\Rak $record): string =>
                                        $record->name . ' (' . $record->code . ')'
                                    )
                                    ->disabled(fn(callable $get) => !$get('warehouse_id'))
                                    ->helperText('Rak akan tersedia setelah gudang dipilih. Rak dengan stock tersedia akan diprioritaskan jika produk sudah dipilih.'),
                                Forms\Components\TextInput::make('available_stock_display')
                                    ->label('Stock Tersedia')
                                    ->disabled()
                                    ->reactive()
                                    ->dehydrated(false)
                                    ->default(function ($get) {
                                        $productId = $get('product_id');
                                        $warehouseId = $get('warehouse_id');

                                        if ($productId && $warehouseId) {
                                            $stock = \App\Models\InventoryStock::where('product_id', $productId)
                                                ->where('warehouse_id', $warehouseId)
                                                ->first();
                                            return number_format($stock ? $stock->qty_available : 0, 2);
                                        }

                                        return '0.00';
                                    })
                                    ->extraInputAttributes(function ($get) {
                                        $productId = $get('product_id');
                                        $warehouseId = $get('warehouse_id');
                                        $quantity = (float) $get('quantity');

                                        if ($productId && $warehouseId) {
                                            $stock = \App\Models\InventoryStock::where('product_id', $productId)
                                                ->where('warehouse_id', $warehouseId)
                                                ->first();
                                            $available = $stock ? $stock->qty_available : 0;
                                            if ($available >= $quantity) {
                                                return ['class' => 'text-green-600 font-semibold'];
                                            } else {
                                                return ['class' => 'text-red-600 font-semibold'];
                                            }
                                        }
                                        return ['class' => 'text-gray-500'];
                                    }),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan')
                                    ->rows(2)
                                    ->columnSpanFull()
                                    ->default(null),
                            ])
                            ->columns(3)
                            ->collapsible()
                            ->defaultItems(0)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ]),

                Forms\Components\Section::make('Informasi Tambahan')
                    ->schema([
                        Forms\Components\Placeholder::make('total_cost_display')
                            ->label('Total Bahan')
                            ->content(function ($get) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $total += HelperController::parseIndonesianMoney($item['total_cost'] ?? '0');
                                }
                                return 'Rp ' . number_format($total, 2, ',', '.');
                            }),
                        Forms\Components\Placeholder::make('labor_cost_display')
                            ->label('Biaya Tenaga Kerja')
                            ->content(function ($get) {
                                $productionPlanId = $get('production_plan_id');
                                if ($productionPlanId) {
                                    $productionPlan = \App\Models\ProductionPlan::with('billOfMaterial')->find($productionPlanId);
                                    if ($productionPlan && $productionPlan->billOfMaterial) {
                                        $labor = $productionPlan->billOfMaterial->labor_cost ?? 0;
                                        return 'Rp ' . number_format($labor, 2, ',', '.');
                                    }
                                }
                                return 'Rp 0,00';
                            }),
                        Forms\Components\Placeholder::make('overhead_cost_display')
                            ->label('Biaya Overhead')
                            ->content(function ($get) {
                                $productionPlanId = $get('production_plan_id');
                                if ($productionPlanId) {
                                    $productionPlan = \App\Models\ProductionPlan::with('billOfMaterial')->find($productionPlanId);
                                    if ($productionPlan && $productionPlan->billOfMaterial) {
                                        $overhead = $productionPlan->billOfMaterial->overhead_cost ?? 0;
                                        return 'Rp ' . number_format($overhead, 2, ',', '.');
                                    }
                                }
                                return 'Rp 0,00';
                            }),
                        Forms\Components\Placeholder::make('total_overall_display')
                            ->label('Total Keseluruhan')
                            ->content(function ($get) {
                                $totalMaterial = 0;
                                $items = $get('items') ?? [];
                                foreach ($items as $item) {
                                    $totalMaterial += HelperController::parseIndonesianMoney($item['total_cost'] ?? '0');
                                }
                                $labor = 0;
                                $overhead = 0;
                                $productionPlanId = $get('production_plan_id');
                                if ($productionPlanId) {
                                    $productionPlan = \App\Models\ProductionPlan::with('billOfMaterial')->find($productionPlanId);
                                    if ($productionPlan && $productionPlan->billOfMaterial) {
                                        $labor = $productionPlan->billOfMaterial->labor_cost ?? 0;
                                        $overhead = $productionPlan->billOfMaterial->overhead_cost ?? 0;
                                    }
                                }
                                $total = $totalMaterial + $labor + $overhead;
                                return 'Rp ' . number_format($total, 2, ',', '.');
                            }),
                        Forms\Components\Hidden::make('total_cost'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Umum')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('issue_number')
                    ->label('Nomor Issue')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productionPlan.plan_number')
                    ->label('Rencana Produksi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productionPlan.name')
                    ->label('Nama Rencana')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipe')
                    ->colors([
                        'primary' => 'issue',
                        'warning' => 'return',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'issue' => 'Ambil Barang',
                        'return' => 'Retur Barang',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('issue_date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'pending_approval',
                        'success' => 'approved',
                        'primary' => 'completed',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'pending_approval' => 'Menunggu Persetujuan',
                        'approved' => 'Disetujui',
                        'completed' => 'Selesai',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Biaya')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Disetujui Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe')
                    ->options([
                        'issue' => 'Ambil Barang',
                        'return' => 'Retur Barang',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'pending_approval' => 'Menunggu Persetujuan',
                        'approved' => 'Disetujui',
                        'completed' => 'Selesai',
                    ]),
                Tables\Filters\SelectFilter::make('production_plan_id')
                    ->label('Rencana Produksi')
                    ->relationship('productionPlan', 'plan_number')
                    ->getOptionLabelFromRecordUsing(function (\App\Models\ProductionPlan $record) {
                        return $record->plan_number . ' - ' . $record->name;
                    })
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('issue_date')
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
                                fn(Builder $query, $date): Builder => $query->whereDate('issue_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('issue_date', '<=', $date),
                            );
                    }),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()->color('primary'),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('request_approval')
                        ->label('Request Approval')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->visible(fn(MaterialIssue $record) => $record->isDraft() && !$record->approved_by)
                        ->requiresConfirmation()
                        ->modalHeading('Request Approval Material Issue')
                        ->modalDescription('Apakah Anda yakin ingin mengirim request approval untuk Material Issue ini?')
                        ->action(function (MaterialIssue $record) {
                            // Validate stock before request approval
                            $stockValidation = static::validateStockAvailability($record);
                            if (!$stockValidation['valid']) {
                                Notification::make()
                                    ->title('Tidak Dapat Request Approval')
                                    ->body($stockValidation['message'])
                                    ->danger()
                                    ->duration(10000)
                                    ->send();
                                return;
                            }
                            // Super Admin bisa approve dari semua cabang, user lain harus di cabang yang sama
                            $currentUser = \Illuminate\Support\Facades\Auth::user();
                            if ($currentUser && $currentUser->hasRole('Super Admin')) {
                                // Super Admin bisa approve dari semua cabang
                                $warehouseApprover = \App\Models\User::whereHas('permissions', function ($query) {
                                    $query->where('name', 'approve warehouse');
                                })
                                    ->where('cabang_id', $record->warehouse->cabang_id ?? null)
                                    ->first();

                                // Jika tidak ada di cabang yang sama, ambil Super Admin sebagai approver
                                if (!$warehouseApprover) {
                                    $warehouseApprover = $currentUser;
                                }
                            } else {
                                // User biasa harus di cabang yang sama
                                $warehouseApprover = \App\Models\User::where('cabang_id', $record->warehouse->cabang_id ?? null)
                                    ->whereHas('permissions', function ($query) {
                                        $query->where('name', 'approve warehouse');
                                    })
                                    ->first();
                            }

                            if ($warehouseApprover) {
                                $record->update([
                                    'approved_by' => $warehouseApprover->id,
                                    'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
                                    // JANGAN set approved_at di sini, biarkan null sampai di-approve
                                    // 'approved_at' => now(),
                                ]);

                                Notification::make()
                                    ->title('Request Approval Terkirim')
                                    ->body("Material Issue {$record->issue_number} telah dikirim untuk approval gudang.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Tidak Ada Approver Gudang')
                                    ->body('Tidak ditemukan approver gudang untuk cabang ini.')
                                    ->warning()
                                    ->send();
                            }
                        }),
                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(function (MaterialIssue $record) {
                            $currentUser = \Illuminate\Support\Facades\Auth::user();
                            if (!$currentUser) return false;

                            // Super Admin can approve all pending approval records
                            if ($currentUser->hasRole('Super Admin')) {
                                return $record->isPendingApproval();
                            }

                            // Users with 'approve warehouse' permission can approve if they are assigned or if no one is assigned
                            return $record->isPendingApproval() &&
                                userHasPermission('approve warehouse') &&
                                (!$record->approved_by || $record->approved_by === $currentUser->id);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Approve Material Issue')
                        ->modalDescription('Setelah di-approve, Material Issue dapat diproses menjadi Completed.')
                        ->action(function (MaterialIssue $record) {
                            // Validate stock before approval
                            $stockValidation = static::validateStockAvailability($record);
                            if (!$stockValidation['valid']) {
                                Notification::make()
                                    ->title('Tidak Dapat Menyetujui Material Issue')
                                    ->body($stockValidation['message'])
                                    ->danger()
                                    ->duration(10000)
                                    ->send();
                                return;
                            }

                            $record->update([
                                'approved_at' => now(),
                                'approved_by' => \Illuminate\Support\Facades\Auth::id(), // Set Super Admin sebagai approver jika belum ada
                                'status' => MaterialIssue::STATUS_APPROVED,
                            ]);

                            Notification::make()
                                ->title('Material Issue Di-approve')
                                ->body("Material Issue {$record->issue_number} telah di-approve dan siap untuk diproses.")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(function (MaterialIssue $record) {
                            $currentUser = \Illuminate\Support\Facades\Auth::user();
                            if (!$currentUser) return false;

                            // Super Admin can reject all pending approval records
                            if ($currentUser->hasRole('Super Admin')) {
                                return $record->isPendingApproval();
                            }

                            // Users with 'approve warehouse' permission can reject if they are assigned or if no one is assigned
                            return $record->isPendingApproval() &&
                                userHasPermission('approve warehouse') &&
                                (!$record->approved_by || $record->approved_by === $currentUser->id);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Reject Material Issue')
                        ->modalDescription('Berikan alasan penolakan:')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('rejection_reason')
                                ->label('Alasan Penolakan')
                                ->required()
                                ->maxLength(500),
                        ])
                        ->action(function (MaterialIssue $record, array $data) {
                            $record->update([
                                'approved_by' => null,
                                'approved_at' => null,
                                'status' => MaterialIssue::STATUS_DRAFT,
                                'notes' => ($record->notes ? $record->notes . "\n\n" : '') .
                                    "DITOLAK: {$data['rejection_reason']} - " . now()->format('Y-m-d H:i:s'),
                            ]);

                            Notification::make()
                                ->title('Material Issue Ditolak')
                                ->body("Material Issue {$record->issue_number} telah ditolak.")
                                ->warning()
                                ->send();
                        }),
                    Tables\Actions\Action::make('complete')
                        ->label('Selesai')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(function (MaterialIssue $record) {
                            $currentUser = \Illuminate\Support\Facades\Auth::user();
                            if (!$currentUser) return false;

                            // Super Admin can complete all approved records
                            if ($currentUser->hasRole('Super Admin')) {
                                return $record->isApproved();
                            }

                            // Users with 'approve warehouse' permission can complete approved records
                            return $record->isApproved() && userHasPermission('approve warehouse');
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Selesaikan Material Issue')
                        ->modalDescription('Apakah Anda yakin ingin menyelesaikan Material Issue ini? Stock akan dikurangi dan journal entry akan dibuat.')
                        ->action(function (MaterialIssue $record) {
                            // Validate stock before completion
                            $stockValidation = static::validateStockAvailability($record);
                            if (!$stockValidation['valid']) {
                                Notification::make()
                                    ->title('Tidak Dapat Menyelesaikan Material Issue')
                                    ->body($stockValidation['message'])
                                    ->danger()
                                    ->duration(10000)
                                    ->send();
                                return;
                            }

                            $record->update(['status' => MaterialIssue::STATUS_COMPLETED]);

                            Notification::make()
                                ->title('Material Issue Diselesaikan')
                                ->body("Material Issue {$record->issue_number} telah diselesaikan. Stock dikurangi dan journal entry dibuat.")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('generate_journal')
                        ->label('Generate Jurnal')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->visible(fn(MaterialIssue $record) => $record->status === 'completed' && !$record->journalEntry()->exists())
                        ->requiresConfirmation()
                        ->action(function (MaterialIssue $record) {
                            try {
                                $journalService = app(ManufacturingJournalService::class);

                                if ($record->type === 'issue') {
                                    $journalService->generateJournalForMaterialIssue($record);
                                } else {
                                    $journalService->generateJournalForMaterialReturn($record);
                                }

                                Notification::make()
                                    ->title('Jurnal Berhasil Dibuat')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Gagal Membuat Jurnal')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Pengambilan Bahan Baku (Material Issue)</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Material Issue adalah proses pengambilan bahan baku dari gudang untuk keperluan produksi, baik untuk issue (pengambilan) maupun return (pengembalian).</li>' .
                            '<li><strong>Tipe:</strong> <em>Issue</em> (pengambilan bahan untuk produksi), <em>Return</em> (pengembalian bahan yang tidak terpakai).</li>' .
                            '<li><strong>Status Flow:</strong> Draft → Pending Approval → Approved → Completed. Membutuhkan approval sebelum bahan dapat diambil.</li>' .
                            '<li><strong>Validasi:</strong> <em>Stock Check</em> otomatis - sistem memverifikasi ketersediaan stock di gudang sebelum approval. <em>Cost Calculation</em> otomatis berdasarkan harga pokok.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan <em>Production Plan</em> (dibuat otomatis), <em>Manufacturing Order</em> (proses produksi), dan <em>Inventory</em> (pengurangan stock).</li>' .
                            '<li><strong>Actions:</strong> <em>Request Approval</em> (draft → pending), <em>Approve/Reject</em> (pending → approved/rejected), <em>Complete</em> (approved → completed, stock berkurang), <em>Generate Journal</em> (untuk akuntansi).</li>' .
                            '<li><strong>Permissions:</strong> <em>view any material issue</em>, <em>create material issue</em>, <em>update material issue</em>, <em>delete material issue</em>, <em>restore material issue</em>, <em>force-delete material issue</em>.</li>' .
                            '<li><strong>Stock Management:</strong> Stock bahan baku otomatis berkurang saat completed. Sistem mencegah pengambilan jika stock tidak mencukupi.</li>' .
                            '<li><strong>Accounting:</strong> Journal entry otomatis dibuat untuk mencatat pengeluaran bahan baku ke Work in Progress (WIP).</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialIssues::route('/'),
            'create' => Pages\CreateMaterialIssue::route('/create'),
            'view' => Pages\ViewMaterialIssue::route('/{record}'),
            'edit' => Pages\EditMaterialIssue::route('/{record}/edit'),
        ];
    }

    /**
     * Validate stock availability for material issue items
     */
    protected static function validateStockAvailability(MaterialIssue $materialIssue): array
    {
        $materialIssue->loadMissing('items.product');

        $insufficientStock = [];
        $outOfStock = [];

        foreach ($materialIssue->items as $item) {
            $inventoryStock = \App\Models\InventoryStock::where('product_id', $item->product_id)
                ->where('warehouse_id', $item->warehouse_id ?? $materialIssue->warehouse_id)
                ->first();

            $availableQty = $inventoryStock ? $inventoryStock->qty_available : 0;
            $requiredQty = $item->quantity;

            if ($availableQty <= 0) {
                $outOfStock[] = "{$item->product->name} (Stock: 0)";
            } elseif ($availableQty < $requiredQty) {
                $insufficientStock[] = "{$item->product->name} (Dibutuhkan: {$requiredQty}, Tersedia: {$availableQty})";
            }
        }

        if (!empty($outOfStock)) {
            return [
                'valid' => false,
                'message' => 'Stock habis untuk produk berikut: ' . implode(', ', $outOfStock)
            ];
        }

        if (!empty($insufficientStock)) {
            return [
                'valid' => false,
                'message' => 'Stock tidak mencukupi untuk produk berikut: ' . implode(', ', $insufficientStock)
            ];
        }

        return [
            'valid' => true,
            'message' => 'Stock tersedia untuk semua item'
        ];
    }
}
