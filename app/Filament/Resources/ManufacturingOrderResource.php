<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManufacturingOrderResource\Pages;
use App\Filament\Resources\ManufacturingOrderResource\Pages\ViewManufacturingOrder;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\InventoryStock;
use Illuminate\Support\Facades\Gate;
use App\Models\ManufacturingOrder;
use App\Models\Product;
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
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(Cabang::all()->mapWithKeys(function ($cabang) {
                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                            }))
                            ->searchable()
                            ->preload()
                            ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn () => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->required()
                            ->helperText('Pilih cabang untuk manufacturing order ini'),
                        Select::make('production_plan_id')
                            ->label('Rencana Produksi')
                            ->relationship('productionPlan', 'plan_number', function (Builder $query) {
                                $query->where('status', 'in_progress');
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Rencana Produksi harus dipilih'
                            ])
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                if ($state) {
                                    $productionPlan = \App\Models\ProductionPlan::with('product', 'billOfMaterial.items.product')->find($state);
                                    if ($productionPlan) {
                                        // Auto-fill start_date and end_date from Production Plan
                                        if ($productionPlan->start_date) {
                                            $set('start_date', \Carbon\Carbon::parse($productionPlan->start_date)->format('Y-m-d H:i:s'));
                                        }
                                        if ($productionPlan->end_date) {
                                            $set('end_date', \Carbon\Carbon::parse($productionPlan->end_date)->format('Y-m-d H:i:s'));
                                        }

                                        // Check if there's a completed Material Issue for this Production Plan
                                        $materialIssue = \App\Models\MaterialIssue::where('production_plan_id', $productionPlan->id)
                                            ->where('status', 'completed')
                                            ->with('items.product')
                                            ->first();

                                        if ($materialIssue) {
                                            // Auto-load materials from completed Material Issue
                                            $items = [];
                                            foreach ($materialIssue->items as $issueItem) {
                                                $items[] = [
                                                    'product_id' => $issueItem->product_id,
                                                    'uom_id' => $issueItem->uom_id,
                                                    'quantity' => $issueItem->quantity,
                                                    'notes' => null,
                                                ];
                                            }
                                            $set('items', $items);
                                        } elseif ($productionPlan->billOfMaterial) {
                                            // Fallback to BOM if no completed Material Issue exists
                                            $items = [];
                                            foreach ($productionPlan->billOfMaterial->items as $bomItem) {
                                                $items[] = [
                                                    'product_id' => $bomItem->product_id,
                                                    'uom_id' => $bomItem->uom_id,
                                                    'quantity' => $bomItem->quantity * $productionPlan->quantity,
                                                    'notes' => null,
                                                ];
                                            }
                                            $set('items', $items);
                                        }
                                    }
                                }
                            })
                            ->getOptionLabelFromRecordUsing(function (\App\Models\ProductionPlan $record) {
                                return $record->plan_number . ' - ' . $record->name . ' (' . $record->product->name . ' - ' . $record->quantity . ' ' . $record->uom->name . ')';
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
                                    ->validationMessages([
                                        'required' => 'Material harus dipilih'
                                    ])
                                    ->options(Product::where('is_raw_material', true)->pluck('name', 'id'))
                                    ->getOptionLabelFromRecordUsing(function ($value) {
                                        $product = Product::find($value);
                                        return $product ? "({$product->sku}) {$product->name}" : '';
                                    })
                                    ->helperText(function ($get) {
                                        $inventoryStock = InventoryStock::where('product_id', $get('product_id'))
                                            ->where('warehouse_id', $get('warehouse_id'))
                                            ->first();
                                        if ($inventoryStock) {
                                            return "Stock Material : {$inventoryStock->qty_available}";
                                        }
                                        return "Stock Material : 0";
                                    })
                                    ->disabled(), // Disabled since loaded from BOM
                                Select::make('uom_id')
                                    ->label('Satuan')
                                    ->options(UnitOfMeasure::all()->mapWithKeys(fn($uom) => [$uom->id => "({$uom->abbreviation}) {$uom->name}"])->toArray())
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Satuan harus dipilih'
                                    ])
                                    ->disabled(), // Disabled since loaded from Material Issue
                                TextInput::make('quantity')
                                    ->label('Quantity Required (Dibutuhkan)')
                                    ->numeric()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Quantity wajib diisi',
                                        'numeric' => 'Quantity harus berupa angka'
                                    ])
                                    ->disabled(), // Disabled since loaded from BOM
                                TextInput::make('notes')
                                    ->label('Notes')
                                    ->maxLength(255),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->disabled() // Entire repeater disabled, for view only
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
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                    ]),
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
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Manufacturing Order (MO)</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Manufacturing Order (MO) adalah instruksi produksi yang dibuat dari Production Plan, mengatur proses manufaktur produk berdasarkan Bill of Material (BOM).</li>' .
                            '<li><strong>Status Flow:</strong> Draft → In Progress → Completed. MO dibuat otomatis dari Production Plan yang disetujui.</li>' .
                            '<li><strong>Validasi:</strong> <em>Stock Check</em> otomatis dilakukan sebelum memulai produksi. Sistem akan memverifikasi ketersediaan bahan baku dari BOM.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan <em>Production Plan</em> (sumber), <em>Bill of Material</em> (resep), dan <em>Production</em> (pelaksanaan produksi).</li>' .
                            '<li><strong>Actions:</strong> <em>Start Production</em> (draft → in_progress, hanya jika stock cukup), <em>View Production</em> (lihat detail produksi), <em>Complete</em> (selesai produksi).</li>' .
                            '<li><strong>Permissions:</strong> <em>view any manufacturing order</em>, <em>create manufacturing order</em>, <em>update manufacturing order</em>, <em>request manufacturing order</em> (untuk start production), <em>response manufacturing order</em> (untuk approval).</li>' .
                            '<li><strong>Material Management:</strong> Sistem otomatis mengurangi stock bahan baku saat produksi dimulai dan menambah stock produk jadi saat selesai.</li>' .
                            '<li><strong>Costing:</strong> Biaya produksi dihitung berdasarkan BOM yang digunakan, termasuk material cost, labor cost, dan overhead cost.</li>' .
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
