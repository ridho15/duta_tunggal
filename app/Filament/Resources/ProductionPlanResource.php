<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductionPlanResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\ProductionPlan;
use App\Models\SaleOrder;
use App\Models\BillOfMaterial;
use App\Models\Warehouse;
use App\Services\ProductionPlanService;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionPlanResource extends Resource
{
    protected static ?string $model = ProductionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Manufacturing Order';

    protected static ?string $navigationLabel = 'Rencana Produksi';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Informasi Rencana Produksi')
                    ->schema([
                        TextInput::make('plan_number')
                            ->label('Nomor Rencana')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'Nomor rencana tidak boleh kosong',
                                'unique' => 'Nomor rencana sudah digunakan'
                            ])
                            ->suffixAction(FormAction::make('generatePlanNumber')
                                ->icon('heroicon-m-arrow-path')
                                ->tooltip('Generate Nomor Rencana')
                                ->action(function ($set, $get, $state) {
                                    $productionPlanService = app(ProductionPlanService::class);
                                    $set('plan_number', $productionPlanService->generatePlanNumber());
                                }))
                            ->default(function () {
                                $productionPlanService = app(ProductionPlanService::class);
                                return $productionPlanService->generatePlanNumber();
                            }),

                        TextInput::make('name')
                            ->label('Nama Pekerjaan')
                            ->required()
                            ->validationMessages([
                                'required' => 'Nama pekerjaan tidak boleh kosong'
                            ])
                            ->maxLength(255),

                        Radio::make('source_type')
                            ->label('Sumber Produksi')
                            ->options([
                                'sale_order' => 'Dari Pesanan Penjualan',
                                'manual' => 'Input Manual Formula Produksi'
                            ])
                            ->default('manual')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                // Reset dependent fields when source type changes
                                $set('sale_order_id', null);
                                $set('bill_of_material_id', null);
                                $set('product_id', null);
                                $set('quantity', null);
                                $set('uom_id', null);
                            }),

                        Select::make('sale_order_id')
                            ->label('Pesanan Penjualan')
                            ->options(function () {
                                $productionPlanService = app(ProductionPlanService::class);
                                return $productionPlanService->getSaleOrderOptions();
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->visible(fn($get) => $get('source_type') === 'sale_order')
                            ->reactive()
                            ->dehydrated()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                if ($state && $get('source_type') === 'sale_order') {
                                    // Reset product selection when sale order changes
                                    $set('product_id', null);
                                    $set('quantity', null);
                                    $set('uom_id', null);
                                }
                            }),

                        Select::make('bill_of_material_id')
                            ->label('Formula Produksi (BOM)')
                            ->options(function () {
                                $productionPlanService = app(ProductionPlanService::class);
                                return $productionPlanService->getBillOfMaterialOptions();
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->visible(fn($get) => $get('source_type') === 'manual')
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                if ($state && $get('source_type') === 'manual') {
                                    $bom = BillOfMaterial::with('product')->find($state);
                                    if ($bom) {
                                        $set('product_id', $bom->product_id);
                                        $set('uom_id', $bom->uom_id);
                                    }
                                }
                            })
                            ->rules([
                                function ($get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        if ($get('source_type') === 'manual' && $value) {
                                            $bom = BillOfMaterial::find($value);
                                            if (!$bom) {
                                                $fail('BOM yang dipilih tidak valid.');
                                                return;
                                            }

                                            if (!$bom->is_active) {
                                                $fail('BOM yang dipilih tidak aktif.');
                                                return;
                                            }

                                            if ($bom->isInUse()) {
                                                // Allow but show warning - BOM sedang digunakan production plan lain
                                                // This is just informational, not blocking
                                            }
                                        }
                                    };
                                }
                            ]),

                        Select::make('product_id')
                            ->label('Produk')
                            ->options(function ($get) {
                                $sourceType = $get('source_type');
                                $saleOrderId = $get('sale_order_id');

                                if ($sourceType === 'sale_order' && $saleOrderId) {
                                    // Show only products from selected sale order
                                    $saleOrder = SaleOrder::with(['saleOrderItem.product', 'saleOrderItem.product.uom'])->find($saleOrderId);
                                    if ($saleOrder && $saleOrder->saleOrderItem->count() > 0) {
                                        return $saleOrder->saleOrderItem->mapWithKeys(function ($item) {
                                            $product = $item->product;
                                            $uom = $product->uom ?? null;
                                            if ($product && $uom) {
                                                return [$product->id => "({$product->sku}) {$product->name} - Qty: {$item->quantity} {$uom->name}"];
                                            }
                                            return [];
                                        })->filter()->toArray();
                                    }
                                    return [];
                                } elseif ($sourceType === 'manual') {
                                    // For manual, get product from BOM selection
                                    $bomId = $get('bill_of_material_id');
                                    if ($bomId) {
                                        $bom = BillOfMaterial::with('product')->find($bomId);
                                        if ($bom && $bom->product) {
                                            return [$bom->product->id => "({$bom->product->sku}) {$bom->product->name}"];
                                        }
                                    }
                                    return [];
                                }

                                return [];
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->disabled(fn($get) => $get('source_type') === 'manual')
                            ->dehydrated()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                if ($state && $get('source_type') === 'sale_order') {
                                    $saleOrderId = $get('sale_order_id');

                                    // Get quantity and uom from sale order item
                                    $saleOrder = SaleOrder::with(['saleOrderItem' => function ($query) use ($state) {
                                        $query->where('product_id', $state);
                                    }, 'saleOrderItem.product'])->find($saleOrderId);

                                    if ($saleOrder && $saleOrder->saleOrderItem->count() > 0) {
                                        $item = $saleOrder->saleOrderItem->first();
                                        if ($item) {
                                            $set('quantity', $item->quantity ?? null);
                                            // Get uom_id from product since sale_order_items doesn't have uom_id
                                            $set('uom_id', $item->product->uom_id ?? null);
                                        }
                                    }
                                }
                            }),

                        TextInput::make('quantity')
                            ->label('Kuantitas')
                            ->numeric()
                            ->required()
                            ->validationMessages([
                                'required' => 'Kuantitas tidak boleh kosong',
                                'numeric' => 'Kuantitas harus berupa angka'
                            ])
                            ->minValue(0.01)
                            ->disabled(fn($get) => $get('source_type') === 'sale_order')
                            ->dehydrated(),

                        Select::make('uom_id')
                            ->label('Satuan')
                            ->relationship('uom', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return $record ? $record->name . ' (' . $record->abbreviation . ')' : '-';
                            })
                            ->disabled(fn($get) => $get('source_type') === 'sale_order')
                            ->dehydrated(),

                        Select::make('warehouse_id')
                            ->label('Gudang Produksi')
                            ->relationship('warehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Gudang produksi harus dipilih'
                            ])
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return $record ? "({$record->kode}) {$record->name}" : '-';
                            })
                            ->helperText('Gudang yang akan digunakan untuk sourcing material dan penyimpanan finished goods'),

                        DateTimePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal mulai tidak boleh kosong'
                            ]),

                        DateTimePicker::make('end_date')
                            ->label('Tanggal Selesai')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal selesai tidak boleh kosong'
                            ])
                            ->after('start_date'),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                        Checkbox::make('auto_schedule')
                            ->label('Jadwalkan Langsung')
                            ->helperText('Centang untuk langsung mengubah status menjadi SCHEDULED setelah dibuat')
                            ->default(false)
                            ->reactive()
                            ->visible(fn($context, $record) => $context === 'create' || ($record && $record->status === 'draft'))
                            ->disabled(fn($record) => $record && $record->status !== 'draft'),
                    ]),

                Placeholder::make('status_info')
                    ->label('Status')
                    ->content(function ($record) {
                        return $record ? Str::upper($record->status) : 'DRAFT';
                    })
                    ->visible(fn($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plan_number')
                    ->label('Nomor Rencana')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('billOfMaterial.code')
                    ->label('Kode BOM')
                    ->formatStateUsing(function ($state, $record) {
                        return $state;
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('billOfMaterial', function ($query) use ($search) {
                            $query->where('code', 'LIKE', '%' . $search . '%');
                        });
                    }),

                TextColumn::make('name')
                    ->label('Nama Pekerjaan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('source_type')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'sale_order' => 'Pesanan Penjualan',
                            'manual' => 'Manual',
                            default => '-'
                        };
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'sale_order' => 'success',
                            'manual' => 'warning',
                            default => 'gray'
                        };
                    }),

                TextColumn::make('product')
                    ->label('Produk')
                    ->formatStateUsing(function ($state) {
                        if ($state) {
                            return "({$state->sku}) {$state->name}";
                        }
                        return '-';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('product', function ($query) use ($search) {
                            $query->where('name', 'LIKE', '%' . $search . '%')
                                ->orWhere('sku', 'LIKE', '%' . $search . '%');
                        });
                    }),

                TextColumn::make('quantity')
                    ->label('Kuantitas')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('uom.name')
                    ->label('Satuan'),

                TextColumn::make('warehouse')
                    ->label('Gudang Produksi')
                    ->formatStateUsing(function ($state) {
                        if ($state) {
                            return "({$state->kode}) {$state->name}";
                        }
                        return '-';
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        $query->whereHas('warehouse', function ($query) use ($search) {
                            $query->where('name', 'LIKE', '%' . $search . '%')
                                ->orWhere('kode', 'LIKE', '%' . $search . '%');
                        });
                    }),

                TextColumn::make('start_date')
                    ->label('Tanggal Mulai')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('end_date')
                    ->label('Tanggal Selesai')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'scheduled' => 'warning',
                            'in_progress' => 'info',
                            'completed' => 'success',
                            'cancelled' => 'danger',
                            default => 'gray'
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    }),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('source_type')
                    ->label('Sumber Produksi')
                    ->options([
                        'sale_order' => 'Dari Pesanan Penjualan',
                        'manual' => 'Input Manual',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    Tables\Actions\Action::make('schedule')
                        ->label('Jadwalkan')
                        ->icon('heroicon-o-calendar-days')
                        ->color('warning')
                        ->visible(function ($record) {
                            return $record->status === 'draft';
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Jadwalkan Rencana Produksi')
                        ->modalDescription(function ($record) {
                            $bomExists = $record->billOfMaterial !== null;
                            $description = 'Apakah Anda yakin ingin menjadwalkan rencana produksi ini? Status akan berubah menjadi SCHEDULED';

                            if ($bomExists) {
                                $description .= ' dan MaterialIssue akan dibuat otomatis dari BOM.';
                            } else {
                                $description .= '.

⚠️ PERHATIAN: Tidak ada BOM yang terkait. MaterialIssue tidak akan dibuat otomatis.';
                            }

                            return $description;
                        })
                        ->modalSubmitActionLabel('Jadwalkan')
                        ->action(function ($record) {
                            if ($record->status !== 'draft') {
                                Notification::make()
                                    ->title('Rencana sudah dijadwalkan')
                                    ->info()
                                    ->body('Rencana produksi ini tidak berada pada status draft.')
                                    ->send();

                                return;
                            }

                            // Validate BOM exists before scheduling
                            if (!$record->billOfMaterial) {
                                Notification::make()
                                    ->title('BOM Tidak Ditemukan')
                                    ->danger()
                                    ->body('Tidak dapat menjadwalkan rencana produksi tanpa BOM. Silakan tambahkan BOM terlebih dahulu.')
                                    ->send();

                                return;
                            }

                            try {
                                DB::transaction(function () use ($record) {
                                    $record->update(['status' => 'scheduled']);

                                    // Check if MaterialIssue was created by the model event
                                    $materialIssue = \App\Models\MaterialIssue::where('production_plan_id', $record->id)
                                        ->where('type', 'issue')
                                        ->first();

                                    if ($materialIssue) {
                                        HelperController::setLog(
                                            message: 'Production plan dijadwalkan dan MaterialIssue dibuat otomatis.',
                                            model: $record
                                        );

                                        HelperController::sendNotification(
                                            isSuccess: true,
                                            title: 'Berhasil',
                                            message: "Rencana produksi berhasil dijadwalkan dan MaterialIssue {$materialIssue->issue_number} telah dibuat otomatis."
                                        );
                                    } else {
                                        // Fallback: try to create MaterialIssue manually if event didn't work
                                        $manufacturingService = app(\App\Services\ManufacturingService::class);
                                        $materialIssue = $manufacturingService->createMaterialIssueForProductionPlan($record);

                                        if ($materialIssue) {
                                            HelperController::setLog(
                                                message: 'Production plan dijadwalkan dan MaterialIssue dibuat otomatis (fallback).',
                                                model: $record
                                            );

                                            HelperController::sendNotification(
                                                isSuccess: true,
                                                title: 'Berhasil',
                                                message: "Rencana produksi berhasil dijadwalkan dan MaterialIssue {$materialIssue->issue_number} telah dibuat otomatis."
                                            );
                                        } else {
                                            HelperController::setLog(
                                                message: 'Production plan dijadwalkan tapi MaterialIssue gagal dibuat.',
                                                model: $record
                                            );

                                            HelperController::sendNotification(
                                                isSuccess: false,
                                                title: 'Berhasil (Dengan Peringatan)',
                                                message: 'Rencana produksi berhasil dijadwalkan, namun MaterialIssue gagal dibuat otomatis. Silakan buat MaterialIssue secara manual.'
                                            );
                                        }
                                    }
                                });
                            } catch (\Throwable $exception) {
                                report($exception);

                                HelperController::sendNotification(
                                    isSuccess: false,
                                    title: 'Gagal menjadwalkan',
                                    message: 'Terjadi kesalahan saat menjadwalkan rencana produksi: ' . $exception->getMessage()
                                );
                            }
                        }),
                    DeleteAction::make(),
                    Tables\Actions\Action::make('cancel_plan')
                        ->label('Cancel Plan')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(function ($record) {
                            return in_array($record->status, ['scheduled', 'in_progress']);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Production Plan')
                        ->modalDescription(function ($record) {
                            $moCount = $record->manufacturingOrders()->count();
                            $miCount = $record->materialIssues()->count();

                            $message = "Apakah Anda yakin ingin membatalkan Production Plan ini?\n\n";
                            $message .= "Status saat ini: " . strtoupper($record->status) . "\n";

                            if ($moCount > 0) {
                                $message .= "Manufacturing Orders yang akan dibatalkan: {$moCount}\n";
                            }
                            if ($miCount > 0) {
                                $message .= "Material Issues yang akan dibatalkan: {$miCount}\n";
                            }

                            $message .= "\nTindakan ini tidak dapat dibatalkan.";

                            return $message;
                        })
                        ->modalSubmitActionLabel('Ya, Batalkan')
                        ->action(function ($record) {
                            // Cancel Production Plan
                            $record->update(['status' => 'cancelled']);

                            // Cancel all related Manufacturing Orders
                            $record->manufacturingOrders()->whereIn('status', ['draft', 'in_progress'])->update(['status' => 'cancelled']);

                            // Cancel all related Material Issues
                            $record->materialIssues()->whereIn('status', ['draft', 'pending_approval', 'approved'])->update(['status' => 'rejected']);

                            \App\Http\Controllers\HelperController::sendNotification(
                                isSuccess: true,
                                title: "Production Plan Dibatalkan",
                                message: "Production Plan {$record->plan_number} telah berhasil dibatalkan beserta semua Manufacturing Order dan Material Issue yang terkait."
                            );
                        }),
                    Tables\Actions\Action::make('create_mo')
                        ->label('Buat MO')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->visible(function ($record) {
                            return $record->status === 'scheduled' && $record->manufacturingOrders()->count() === 0;
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Buat Manufacturing Order')
                        ->modalDescription(function ($record) {
                            // Check material fulfillment before showing modal
                            $canStart = $record->canStartProduction();
                            $summary = $record->getFulfillmentSummary();

                            if (!$canStart) {
                                return "⚠️ PERHATIAN: Bahan baku belum lengkap!\n\n" .
                                    "Total bahan: {$summary['total_materials']}\n" .
                                    "Tersedia penuh: {$summary['fully_available']}\n" .
                                    "Tersedia sebagian: {$summary['partially_available']}\n" .
                                    "Tidak tersedia: {$summary['not_available']}\n\n" .
                                    "Apakah Anda yakin ingin melanjutkan membuat MO?";
                            }

                            return "Semua bahan baku tersedia. Manufacturing Order akan dibuat dengan status Draft.";
                        })
                        ->modalSubmitActionLabel(function ($record) {
                            $canStart = $record->canStartProduction();
                            return $canStart ? 'Buat MO' : 'Buat MO (Dengan Risiko)';
                        })
                        ->action(function ($record) {
                            // Check material fulfillment
                            $canStart = $record->canStartProduction();
                            $summary = $record->getFulfillmentSummary();

                            if (!$canStart) {
                                // Log warning but allow creation
                                \Illuminate\Support\Facades\Log::warning('MO created despite incomplete material fulfillment', [
                                    'production_plan_id' => $record->id,
                                    'fulfillment_summary' => $summary,
                                ]);
                            }

                            // Create Manufacturing Order from Production Plan
                            $manufacturingService = app(\App\Services\ManufacturingService::class);

                            // Find a suitable warehouse that has stock for the materials
                            $defaultWarehouseId = null;
                            if ($record->billOfMaterial && $record->billOfMaterial->items->count() > 0) {
                                $firstMaterialId = $record->billOfMaterial->items->first()->product_id;
                                $warehouseWithStock = \App\Models\InventoryStock::where('product_id', $firstMaterialId)
                                    ->where('qty_available', '>', 0)
                                    ->first();
                                $defaultWarehouseId = $warehouseWithStock ? $warehouseWithStock->warehouse_id : 1; // Default to Gudang Utama
                            } else {
                                $defaultWarehouseId = 1; // Default to Gudang Utama
                            }

                            $mo = \App\Models\ManufacturingOrder::create([
                                'mo_number' => $manufacturingService->generateMoNumber(),
                                'production_plan_id' => $record->id,
                                'product_id' => $record->product_id,
                                'quantity' => $record->quantity,
                                'status' => 'draft',
                                'start_date' => $record->start_date,
                                'end_date' => $record->end_date,
                                'uom_id' => $record->uom_id,
                                'warehouse_id' => $defaultWarehouseId,
                                'rak_id' => null,
                            ]);

                            // Create MO materials from BOM if applicable
                            if ($record->billOfMaterial) {
                                foreach ($record->billOfMaterial->items as $item) {
                                    // Find warehouse that has stock for this specific material
                                    $materialWarehouse = \App\Models\InventoryStock::where('product_id', $item->product_id)
                                        ->where('qty_available', '>', 0)
                                        ->first();

                                    \App\Models\ManufacturingOrderMaterial::create([
                                        'manufacturing_order_id' => $mo->id,
                                        'material_id' => $item->product_id,
                                        'qty_required' => $item->quantity * $record->quantity,
                                        'qty_used' => 0,
                                        'uom_id' => $item->uom_id,
                                        'warehouse_id' => $materialWarehouse ? $materialWarehouse->warehouse_id : $defaultWarehouseId,
                                        'rak_id' => null,
                                    ]);
                                }
                            }

                            $warningMessage = '';
                            if (!$canStart) {
                                $warningMessage = "\n\n⚠️ PERINGATAN: Bahan baku belum lengkap. Pastikan stock tersedia sebelum memproses MO.";
                            }

                            \App\Http\Controllers\HelperController::sendNotification(
                                isSuccess: true,
                                title: "Berhasil",
                                message: "Manufacturing Order {$mo->mo_number} berhasil dibuat dari Production Plan {$record->plan_number}{$warningMessage}"
                            );
                        }),
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
            'index' => Pages\ListProductionPlans::route('/'),
            'create' => Pages\CreateProductionPlan::route('/create'),
            'view' => Pages\ViewProductionPlan::route('/{record}'),
            'edit' => Pages\EditProductionPlan::route('/{record}/edit'),
        ];
    }

    /**
     * Validate stock availability for ProductionPlan scheduling
     */
    public static function validateStockForProductionPlan(ProductionPlan $productionPlan): array
    {
        if (!$productionPlan->billOfMaterial) {
            return ['valid' => true, 'message' => 'No BOM to validate'];
        }

        $insufficientStock = [];
        $outOfStock = [];

        foreach ($productionPlan->billOfMaterial->items as $bomItem) {
            $requiredQty = $bomItem->quantity * $productionPlan->quantity;

            $inventoryStock = \App\Models\InventoryStock::where('product_id', $bomItem->product_id)
                ->where('warehouse_id', $productionPlan->warehouse_id)
                ->first();

            $availableQty = $inventoryStock ? $inventoryStock->qty_available : 0;

            if ($availableQty <= 0) {
                $outOfStock[] = "{$bomItem->product->name} (Stock: 0)";
            } elseif ($availableQty < $requiredQty) {
                $insufficientStock[] = "{$bomItem->product->name} (Dibutuhkan: {$requiredQty}, Tersedia: {$availableQty})";
            }
        }

        if (!empty($outOfStock)) {
            return [
                'valid' => false,
                'message' => 'Stock habis untuk produk berikut: ' . implode(', ', $outOfStock) . '. Tidak dapat menjadwalkan rencana produksi.'
            ];
        }

        if (!empty($insufficientStock)) {
            return [
                'valid' => false,
                'message' => 'Stock tidak mencukupi untuk produk berikut: ' . implode(', ', $insufficientStock) . '. Tidak dapat menjadwalkan rencana produksi.'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Stock tersedia untuk semua item'
        ];
    }
}
