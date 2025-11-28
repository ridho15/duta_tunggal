<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManufacturingOrderResource\Pages;
use App\Filament\Resources\ManufacturingOrderResource\Pages\ViewManufacturingOrder;
use App\Http\Controllers\HelperController;
use App\Models\InventoryStock;
use Illuminate\Support\Facades\Gate;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\ManufacturingService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action as ActionsAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;

class ManufacturingOrderResource extends Resource
{
    protected static ?string $model = ManufacturingOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Manufacturing Order';

    // Position Manufacturing Order as the 4th group
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Manufacturing Order')
                    ->schema([
                        TextInput::make('mo_number')
                            ->required()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'MO Number tidak boleh kosong',
                                'unique' => 'MO number sudah digunakan !'
                            ])
                            ->unique(ignoreRecord: true)
                            ->suffixAction(Action::make('generateMoNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate MO Number')
                                ->action(function ($set, $get, $state) {
                                    $manufacturingOrderService = app(ManufacturingService::class);
                                    $set('mo_number', $manufacturingOrderService->generateMoNumber());
                                }))
                            ->maxLength(255),
                        Select::make('production_plan_id')
                            ->label('Rencana Produksi')
                            ->relationship('productionPlan', 'plan_number', function (Builder $query) {
                                $query->where('status', 'in_progress');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                if ($state) {
                                    $productionPlan = \App\Models\ProductionPlan::with('product', 'billOfMaterial.items.product')->find($state);
                                    if ($productionPlan && $productionPlan->billOfMaterial) {
                                        // Auto-load materials from Production Plan's BOM
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
                                            ];
                                        }
                                        $set('items', $items);
                                    }
                                }
                            })
                            ->getOptionLabelFromRecordUsing(function (\App\Models\ProductionPlan $record) {
                                return $record->plan_number . ' - ' . $record->name . ' (' . $record->product->name . ' - ' . $record->quantity . ' ' . $record->uom->name . ')';
                            }),
                        Select::make('rak_id')
                            ->label('Rak')
                            ->preload()
                            ->searchable(['code', 'name'])
                            ->relationship('rak', 'id')
                            ->getOptionLabelFromRecordUsing(function (Rak $rak) {
                                return "({$rak->code}) {$rak->name}";
                            }),
                        DateTimePicker::make('start_date')
                            ->label('Tanggal Mulai'),
                        DateTimePicker::make('end_date')
                            ->label('Tanggal Selesai'),
                        Repeater::make('items')
                            ->label('Detail Bahan')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Material (Bahan Baku)')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->options(Product::where('is_raw_material', true)->pluck('name', 'id'))
                                    ->getOptionLabelFromRecordUsing(function ($value) {
                                        $product = Product::find($value);
                                        return $product ? "({$product->sku}) {$product->name}" : '';
                                    })
                                    ->helperText(function ($get) {
                                        $inventoryStock = InventoryStock::where('product_id', $get('product_id'))
                                            ->where('warehouse_id', $get('warehouse_id'))
                                            ->when($get('rak_id') != null, function ($query) use ($get) {
                                                $query->where('rak_id', $get('rak_id'));
                                            })
                                            ->first();
                                        if ($inventoryStock) {
                                            return "Stock Material : {$inventoryStock->qty_available}";
                                        }
                                        return "Stock Material : 0";
                                    }),
                                Select::make('uom_id')
                                    ->label('Satuan')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->options(UnitOfMeasure::pluck('name', 'id')),
                                TextInput::make('quantity')
                                    ->label('Quantity Required (Dibutuhkan)')
                                    ->numeric()
                                    ->required()
                                    ->default(0),
                                TextInput::make('cost_per_unit')
                                    ->label('Cost per Unit')
                                    ->numeric()
                                    ->required()
                                    ->default(0),
                                TextInput::make('total_cost')
                                    ->label('Total Cost')
                                    ->numeric()
                                    ->required()
                                    ->default(0),
                                Select::make('warehouse_id')
                                    ->label('Gudang')
                                    ->preload()
                                    ->searchable()
                                    ->options(Warehouse::pluck('name', 'id'))
                                    ->getOptionLabelFromRecordUsing(function ($value) {
                                        $warehouse = Warehouse::find($value);
                                        return $warehouse ? "({$warehouse->kode}) {$warehouse->name}" : '';
                                    }),
                                Select::make('rak_id')
                                    ->label('Rak')
                                    ->preload()
                                    ->searchable()
                                    ->options(Rak::pluck('name', 'id'))
                                    ->getOptionLabelFromRecordUsing(function ($value) {
                                        $rak = Rak::find($value);
                                        return $rak ? "({$rak->code}) {$rak->name}" : '';
                                    }),
                                TextInput::make('notes')
                                    ->label('Notes')
                                    ->maxLength(255),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                    ])
            ]);
    }

    /**
     * Check material availability for a product and quantity
     */
    public static function checkMaterialFulfillment(Product $product, $quantity): array
    {
        $billOfMaterial = $product->billOfMaterial->first();

        if (!$billOfMaterial) {
            return [
                'can_produce' => false,
                'message' => 'Produk ini tidak memiliki Bill of Material',
                'summary' => []
            ];
        }

        $totalMaterials = $billOfMaterial->items->count();
        $fullyAvailable = 0;
        $partiallyAvailable = 0;
        $notAvailable = 0;
        $materialDetails = [];

        foreach ($billOfMaterial->items as $item) {
            $requiredQuantity = $item->quantity * $quantity;

            // Get current stock from inventory
            $currentStock = \App\Models\InventoryStock::where('product_id', $item->product_id)
                ->where('qty_available', '>', 0)
                ->sum('qty_available');

            $availabilityPercentage = $currentStock >= $requiredQuantity ? 100 : ($currentStock > 0 ? ($currentStock / $requiredQuantity) * 100 : 0);

            $materialDetails[] = [
                'material_name' => $item->product->name ?? 'Unknown',
                'required' => $requiredQuantity,
                'available' => $currentStock,
                'percentage' => $availabilityPercentage
            ];

            if ($availabilityPercentage >= 100) {
                $fullyAvailable++;
            } elseif ($availabilityPercentage > 0) {
                $partiallyAvailable++;
            } else {
                $notAvailable++;
            }
        }

        $canProduce = $fullyAvailable === $totalMaterials;

        $message = $canProduce
            ? "✅ Semua bahan baku tersedia untuk produksi."
            : "⚠️ Beberapa bahan baku belum tersedia lengkap.";

        return [
            'can_produce' => $canProduce,
            'message' => $message,
            'summary' => [
                'total_materials' => $totalMaterials,
                'fully_available' => $fullyAvailable,
                'partially_available' => $partiallyAvailable,
                'not_available' => $notAvailable,
            ],
            'details' => $materialDetails
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mo_number')
                    ->label('MO Number')
                    ->searchable(),
                TextColumn::make('productionPlan.plan_number')
                    ->label('Rencana Produksi')
                    ->searchable(),
                TextColumn::make('productionPlan.product')
                    ->label('Product')
                    ->formatStateUsing(function ($state) {
                        return "({$state->sku}) {$state->name}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('productionPlan.product', function ($query) use ($search) {
                            $query->where('sku', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    }),
                TextColumn::make('rak')
                    ->label('Rak')
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('rak', function ($query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%')
                                ->orWhere('name', 'LIKE', '%' . $search . '%');
                        });
                    })->formatStateUsing(function ($state) {
                        return "({$state->code}) {$state->name}";
                    }),
                TextColumn::make('productionPlan.quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('productionPlan.uom.name')
                    ->label('Satuan')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'in_progress' => 'warning',
                            'completed' => 'success',
                            default => '-'
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    }),
                TextColumn::make('start_date')
                    ->dateTime()
                    ->label('Tanggal Mulai')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('end_date')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Tanggal Selesai')
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
                //
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                    ActionsAction::make('Produksi')
                        ->label('Produksi')
                        ->color('success')
                        ->icon('heroicon-o-arrow-right-end-on-rectangle')
                        ->requiresConfirmation()
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request manufacturing order') && $record->status == 'draft';
                        })
                        ->action(function ($record) {
                            // Policy guard: transition draft -> in_progress
                            abort_unless(Gate::forUser(Auth::user())->allows('updateStatus', [$record, 'in_progress']), 403);
                            $manufacturingService = app(ManufacturingService::class);
                            $status = $manufacturingService->checkStockMaterial($record);
                            if ($status) {
                                $record->update([
                                    'status' => 'in_progress'
                                ]);

                                // Create Production record automatically
                                $productionService = app(\App\Services\ProductionService::class);
                                \App\Models\Production::create([
                                    'production_number' => $productionService->generateProductionNumber(),
                                    'manufacturing_order_id' => $record->id,
                                    'production_date' => now()->toDateString(),
                                    'status' => 'draft',
                                ]);

                                HelperController::sendNotification(isSuccess: true, title: "Information", message: "Manufacturing In Progress - Production record created");
                            } else {
                                HelperController::sendNotification(isSuccess: false, title: "Information", message: "Stock material tidak mencukupi");
                            }
                        })
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManufacturingOrders::route('/'),
            'create' => Pages\CreateManufacturingOrder::route('/create'),
            'view' => ViewManufacturingOrder::route('/{record}'),
            'edit' => Pages\EditManufacturingOrder::route('/{record}/edit'),
        ];
    }
}
