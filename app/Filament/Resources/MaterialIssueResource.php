<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialIssueResource\Pages;
use App\Filament\Resources\MaterialIssueResource\RelationManagers;
use App\Http\Controllers\HelperController;
use App\Models\MaterialIssue;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\UnitOfMeasure;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

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
                            ->suffixAction(
                                FormAction::make('generate')
                                    ->icon('heroicon-m-arrow-path')
                                    ->action(function ($set, $get) {
                                        $service = app(ManufacturingService::class);
                                        $type = $get('type') ?? 'issue';
                                        $set('issue_number', $service->generateIssueNumber($type));
                                    })
                            )
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('type')
                            ->label('Tipe')
                            ->options([
                                'issue' => 'Ambil Barang',
                                'return' => 'Retur Barang',
                            ])
                            ->required()
                            ->default('issue')
                            ->reactive()
                            ->afterStateUpdated(function ($set, $state) {
                                $service = app(ManufacturingService::class);
                                $set('issue_number', $service->generateIssueNumber($state));
                            }),
                        Forms\Components\Select::make('production_plan_id')
                            ->label('Rencana Produksi')
                            ->relationship('productionPlan', 'plan_number')
                            ->searchable()
                            ->preload()
                            ->required(fn ($get) => $get('type') === 'issue')
                            ->visible(fn ($get) => $get('type') === 'issue')
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
                                                'warehouse_id' => null,
                                                'rak_id' => null,
                                                'notes' => null,
                                                'inventory_coa_id' => null,
                                            ];
                                        }
                                        $set('items', $items);
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
                            ->relationship('warehouse', 'name')
                            ->searchable('kode', 'name')
                            ->preload()
                            ->required()
                            ->nullable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return \App\Models\Warehouse::where('name', 'like', "%{$search}%")
                                    ->orWhere('kode', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($warehouse) {
                                        return [$warehouse->id => $warehouse->kode . ' - ' . $warehouse->name];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelFromRecordUsing(
                                fn(\App\Models\Warehouse $record): string =>
                                $record->kode . ' - ' . $record->name
                            ),
                        Forms\Components\DatePicker::make('issue_date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now())
                            ->native(false),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'completed' => 'Selesai',
                            ])
                            ->required()
                            ->default('draft'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Detail Bahan')
                    ->description('Bahan baku akan otomatis terisi berdasarkan Formula Produksi yang dipilih. Anda dapat menyesuaikan jumlah yang diambil (misalnya ambil 50% saja dari kebutuhan).')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->label('Items')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Produk (Bahan Baku)')
                                    ->relationship('product', 'name', function (Builder $query) {
                                        return $query->where('is_raw_material', true);
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
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
                                    ->getOptionLabelFromRecordUsing(fn (Product $record) => 
                                        "({$record->sku}) {$record->name}"
                                    ),
                                Forms\Components\Select::make('uom_id')
                                    ->label('Satuan')
                                    ->relationship('uom', 'name')
                                    ->required()
                                    ->preload(),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get) {
                                        $qty = (float) $get('quantity');
                                        $cost = (float) $get('cost_per_unit');
                                        $set('total_cost', $qty * $cost);
                                    }),
                                Forms\Components\TextInput::make('cost_per_unit')
                                    ->label('Harga per Unit')
                                    ->numeric()
                                    ->indonesianMoney()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get) {
                                        $qty = (float) $get('quantity');
                                        $cost = (float) $get('cost_per_unit');
                                        $set('total_cost', $qty * $cost);
                                    }),
                                Forms\Components\TextInput::make('total_cost')
                                    ->label('Total')
                                    ->numeric()
                                    ->indonesianMoney()
                                    ->disabled()
                                    ->dehydrated(),
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->relationship('warehouse', 'name')
                                    ->searchable('kode', 'name')
                                    ->reactive()
                                    ->nullable()
                                    ->required()
                                    ->default(null)
                                    ->options(function (callable $get) {
                                        $productId = $get('product_id');
                                        if ($productId) {
                                            return \App\Models\Warehouse::whereHas('inventoryStock', function ($q) use ($productId) {
                                                $q->where('product_id', $productId)->whereRaw('qty_available - qty_reserved > 0');
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
                                Forms\Components\TextInput::make('available_stock_display')
                                    ->label('Stock Tersedia')
                                    ->disabled()
                                    ->reactive()
                                    ->dehydrated(false)
                                    ->default(function ($get) {
                                        return $get('available_stock_display') ?? '0.00';
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
                                Forms\Components\Select::make('rak_id')
                                    ->label('Rak')
                                    ->relationship('rak', 'name')
                                    ->searchable()
                                    ->nullable()
                                    ->default(null),
                                Forms\Components\Select::make('inventory_coa_id')
                                    ->label('COA Inventory')
                                    ->relationship('inventoryCoa', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->default(null)
                                    ->placeholder('Pilih COA Inventory')
                                    ->getSearchResultsUsing(function (string $search): array {
                                        return \App\Models\ChartOfAccount::where('type', 'Asset')
                                            ->where(function ($query) use ($search) {
                                                $query->where('name', 'like', "%{$search}%")
                                                      ->orWhere('code', 'like', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(function ($coa) {
                                                return [$coa->id => $coa->code . ' - ' . $coa->name];
                                            })
                                            ->toArray();
                                    })
                                    ->getOptionLabelFromRecordUsing(
                                        fn(\App\Models\ChartOfAccount $record): string =>
                                        $record->code . ' - ' . $record->name
                                    )
                                    ->helperText('COA untuk inventory item ini. Jika tidak dipilih, akan menggunakan COA dari issue atau produk.'),
                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan')
                                    ->rows(2)
                                    ->columnSpanFull()
                                    ->default(null),
                            ])
                            ->columns(3)
                            ->collapsible()
                            ->defaultItems(0)
                            ->addActionLabel('Tambah Item')
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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
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
                        'success' => 'completed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
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
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '<=', $date),
                            );
                    }),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('generate_journal')
                        ->label('Generate Jurnal')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->visible(fn (MaterialIssue $record) => $record->status === 'completed' && !$record->journalEntry()->exists())
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
            ])
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
}
