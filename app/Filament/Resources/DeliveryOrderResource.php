<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryOrderResource\Pages;
use App\Filament\Resources\DeliveryOrderResource\Pages\ViewDeliveryOrder;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderApprovalLog;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\AppSetting;
use App\Models\Driver;
use App\Models\InventoryStock;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Support\WarehouseStockOptions;
use App\Exports\DeliveryOrderRecapExport;
use App\Services\DeliveryOrderService;
use App\Services\DeliveryOrderSourceValidator;
use App\Services\SaleOrderDeliveryProgress;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\DatePicker;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;

class DeliveryOrderResource extends Resource
{
    protected static ?string $model = DeliveryOrder::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Pengiriman';

    protected static ?string $navigationLabel = 'Perintah Pengiriman';

    protected static ?string $modelLabel = 'Perintah Pengiriman';

    protected static ?string $pluralModelLabel = 'Perintah Pengiriman';

    // Position Delivery Order after Penjualan groups
    protected static ?int $navigationSort = 1;

    /**
     * ID DO yang sedang diedit (null di halaman Create).
     */
    protected static function editingDeliveryOrderId(mixed $livewire = null): ?int
    {
        if ($livewire && method_exists($livewire, 'getRecord')) {
            $record = $livewire->getRecord();
            if ($record instanceof DeliveryOrder && $record->exists) {
                return (int) $record->getKey();
            }
        }

        return null;
    }

    /**
     * SO yang sudah terhubung ke DO yang sedang diedit (tetap ditampilkan/diizinkan walau kuantitasnya sudah terikat).
     *
     * @return array<int, int>
     */
    protected static function linkedSaleOrderIds(mixed $livewire = null): array
    {
        $id = static::editingDeliveryOrderId($livewire);
        if ($id === null) {
            return [];
        }

        return DeliveryOrder::withoutGlobalScopes()->find($id)?->salesOrders()->withoutGlobalScopes()->pluck('sale_orders.id')->map(fn ($v) => (int) $v)->all() ?? [];
    }

    /**
     * Kuantitas item SO yang masih boleh dialokasikan ke DO ini (kuantitas DO yang sedang diedit tidak dihitung terikat).
     */
    protected static function availableFor(SaleOrderItem $saleOrderItem, mixed $livewire = null): float
    {
        return $saleOrderItem->availableQuantityForDelivery(static::editingDeliveryOrderId($livewire));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Delivery Order')
                    ->schema([
                        TextInput::make('do_number')
                            ->label('Delivery Order Number')
                            ->maxLength(255)
                            ->reactive()
                            ->suffixAction(ActionsAction::make('generateDoNumber')
                                ->icon('heroicon-m-arrow-path') // ikon reload
                                ->tooltip('Generate DO Number')
                                ->action(function ($set, $get, $state) {
                                    $deliveryOrderService = app(DeliveryOrderService::class);
                                    $set('do_number', $deliveryOrderService->generateDoNumber());
                                }))
                            ->required()
                            ->validationMessages([
                                'required' => 'DO Number tidak boleh kosong',
                                'unique' => 'DO number sudah digunakan'
                            ])
                            ->unique(ignoreRecord: true),
                        Select::make('salesOrders')
                            ->label('From Sales')
                            ->statePath('salesOrders') // Explicit state path
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->default([]) // Ensure it's always an array
                            ->dehydrateStateUsing(function ($state) {
                                // Ensure we always send an array, even if empty
                                return is_array($state) ? $state : [];
                            })
                            ->options(function ($livewire) {
                                // SO layak kirim (Disetujui/Dikonfirmasi/Dikirim Sebagian) yang masih punya sisa yang belum
                                // terikat DO manapun; SO yang sudah terhubung ke DO yang sedang diedit tetap tampil.
                                return SaleOrder::with('customer')
                                    ->deliverable(static::linkedSaleOrderIds($livewire))
                                    ->latest('id')
                                    ->get()
                                    ->mapWithKeys(function ($so) {
                                        $customer = $so->customer;
                                        $custName = $customer
                                            ? ($customer->perusahaan ? ($customer->name && $customer->name !== $customer->perusahaan ? "{$customer->perusahaan} ({$customer->name})" : $customer->perusahaan) : ($customer->name ?: 'Customer'))
                                            : 'Customer';
                                        return [$so->id => "{$so->so_number} — {$custName}"];
                                    })
                                    ->toArray();
                            })
                            ->multiple()
                            ->required()
                            ->rule(function ($livewire) {
                                return function (string $attribute, $value, \Closure $fail) use ($livewire) {
                                    $errors = app(DeliveryOrderSourceValidator::class)->errors(
                                        is_array($value) ? $value : [],
                                        static::editingDeliveryOrderId($livewire),
                                        static::linkedSaleOrderIds($livewire)
                                    );

                                    foreach ($errors as $message) {
                                        $fail($message);
                                    }
                                };
                            })
                            ->validationMessages([
                                'required' => 'Minimal satu Sales Order wajib dipilih',
                            ])
                            ->helperText('Hanya Sales Order berstatus Disetujui / Dikonfirmasi / Dikirim Sebagian yang masih memiliki sisa kuantitas dapat dipilih. SO yang digabung harus dari customer dan alamat kirim yang sama.')
                            ->afterStateUpdated(function ($set, $get, $state, $livewire) {
                                $selectedIds = is_array($state) ? $state : [];
                                if (empty($selectedIds)) {
                                    $set('deliveryOrderItem', []);
                                    return;
                                }

                                $listSaleOrder = SaleOrder::with(['customer', 'saleOrderItem.warehouseAllocations'])
                                    ->whereIn('id', $selectedIds)
                                    ->get();

                                // Penggabungan SO hanya untuk customer DAN alamat kirim yang sama
                                $mixErrors = app(DeliveryOrderSourceValidator::class)->mixErrors($selectedIds);
                                if (! empty($mixErrors)) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Sales Order Tidak Dapat Digabung')
                                        ->body(implode(' ', $mixErrors))
                                        ->danger()
                                        ->send();

                                    $firstSo = $listSaleOrder->first();
                                    $selectedIds = [$firstSo->id];
                                    $set('salesOrders', $selectedIds);
                                    $listSaleOrder = collect([$firstSo]);
                                }

                                // H1: auto-set cabang_id from the first selected SO
                                if ($listSaleOrder->isNotEmpty()) {
                                    $set('cabang_id', $listSaleOrder->first()->cabang_id);
                                }

                                $deliveryItems = [];
                                foreach ($listSaleOrder as $saleOrder) {
                                    foreach ($saleOrder->saleOrderItem as $saleOrderItem) {
                                        $remainingQty = static::availableFor($saleOrderItem, $livewire);
                                        // Only add items that still have remaining quantity
                                        if ($remainingQty > 0) {
                                            $warehouseSources = $saleOrderItem->warehouseAllocations
                                                ->map(function ($allocation) {
                                                    return [
                                                        'warehouse_id' => $allocation->warehouse_id,
                                                        'quantity' => (float) $allocation->quantity,
                                                        'rak_id' => null,
                                                    ];
                                                })
                                                ->values()
                                                ->toArray();

                                            if (empty($warehouseSources)) {
                                                $fallbackWarehouseId = $saleOrderItem->warehouse_id;
                                                if (!$fallbackWarehouseId) {
                                                    $cabangId = $saleOrder->cabang_id ?? Auth::user()?->cabang_id;
                                                    $fallbackWarehouseId = \App\Models\Warehouse::where('status', 1)
                                                        ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                                                        ->value('id');
                                                }
                                                if ($fallbackWarehouseId) {
                                                    $warehouseSources = [
                                                        [
                                                            'warehouse_id' => $fallbackWarehouseId,
                                                            'quantity' => (float) $remainingQty,
                                                            'rak_id' => $saleOrderItem->rak_id,
                                                        ]
                                                    ];
                                                }
                                            }

                                            $deliveryItems[] = [
                                                'options_from' => 2,
                                                'sale_order_item_id' => $saleOrderItem->id,
                                                'product_id' => $saleOrderItem->product_id,
                                                'quantity' => $remainingQty,
                                                'warehouseSources' => $warehouseSources,
                                                'reason' => '',
                                            ];
                                        }
                                    }
                                }

                                $set('deliveryOrderItem', $deliveryItems);
                            }),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->searchable()
                            ->preload()
                            ->options(Cabang::orderBy('kode')->limit(50)->get()->mapWithKeys(function ($cabang) {
                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                            }))
                            ->visible(fn() => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn() => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->required()
                            ->validationMessages([
                                'required' => 'Cabang wajib dipilih',
                            ])
                            ->helperText('Diisi otomatis dari Sales Order. Dapat diubah bila perlu.'),
                        DateTimePicker::make('delivery_date')
                            ->label('Tanggal Pengiriman')
                            ->required()
                            ->validationMessages([
                                'required' => 'Tanggal pengiriman wajib diisi',
                                'date' => 'Format tanggal tidak valid'
                            ])
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->helperText('Tentukan tanggal dan waktu pengiriman yang direncanakan'),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->nullable(),
                        // Section untuk memilih item dari sales order
                        Fieldset::make('Barang untuk Dikirim')
                            ->schema([
                                Repeater::make('deliveryOrderItem')
                                    ->relationship('deliveryOrderItem')
                                    ->statePath('deliveryOrderItem')
                                    ->reactive()
                                    ->live()
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->collapsed() // UX: collapsible items for better UX
                                    ->addAction(fn($action) => $action->hidden()) // Hide add button: items come from Sales Order
                                    ->itemLabel(function (array $state): string {
                                        $productId = $state['product_id'] ?? null;
                                        $qty = $state['quantity'] ?? '0';
                                        $productName = '-';
                                        if ($productId) {
                                            static $productCache = [];
                                            if (!isset($productCache[$productId])) {
                                                $product = Product::withoutGlobalScopes()->find($productId);
                                                $productCache[$productId] = $product ? "({$product->sku}) {$product->name}" : '-';
                                            }
                                            $productName = $productCache[$productId];
                                        }
                                        return "Product: {$productName} | Qty: {$qty}";
                                    })
                                    ->defaultItems(function ($get, $livewire) {
                                        $salesOrders = $get('salesOrders') ?? [];
                                        if (!empty($salesOrders)) {
                                            $count = 0;
                                            $listSaleOrder = SaleOrder::with('saleOrderItem')->whereIn('id', $salesOrders)->get();
                                            foreach ($listSaleOrder as $saleOrder) {
                                                $count += $saleOrder->saleOrderItem
                                                    ->filter(fn ($item) => static::availableFor($item, $livewire) > 0)
                                                    ->count();
                                            }
                                            return $count;
                                        }
                                        return 0;
                                    })
                                    ->mutateRelationshipDataBeforeFillUsing(function ($data) {
                                        if ($data['sale_order_item_id']) {
                                            $data['options_from'] = 2;
                                        }
                                        return $data;
                                    })
                                    ->rules([
                                        function (Get $get, $livewire) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get, $livewire) {
                                                $salesOrderIds = $get('salesOrders') ?? [];
                                                if (empty($salesOrderIds) || empty($value)) {
                                                    return;
                                                }

                                                $soItemIds = collect($value)->pluck('sale_order_item_id')->filter()->unique();
                                                $soItems = SaleOrderItem::with('product')->whereIn('id', $soItemIds)->get()->keyBy('id');

                                                // Validasi setiap delivery item
                                                foreach ($value as $deliveryItem) {
                                                    if (!empty($deliveryItem['sale_order_item_id']) && !empty($deliveryItem['quantity'])) {
                                                        $warehouseSources = collect($deliveryItem['warehouseSources'] ?? []);
                                                        if ($warehouseSources->isNotEmpty()) {
                                                            $sourceQty = (float) $warehouseSources->sum(function ($source) {
                                                                return (float) ($source['quantity'] ?? 0);
                                                            });

                                                            if (abs($sourceQty - (float) $deliveryItem['quantity']) > 0.0001) {
                                                                $fail('Total qty sumber gudang harus sama dengan quantity item delivery order.');
                                                                return;
                                                            }

                                                            foreach ($warehouseSources as $source) {
                                                                $sourceWarehouseId = $source['warehouse_id'] ?? null;
                                                                $sourceQtyItem = (float) ($source['quantity'] ?? 0);
                                                                if (!$sourceWarehouseId || $sourceQtyItem <= 0) {
                                                                    $fail('Setiap sumber gudang wajib memiliki gudang dan qty > 0.');
                                                                    return;
                                                                }

                                                                $productId = $deliveryItem['product_id'] ?? null;
                                                                $availableStock = app(\App\Services\StockAvailability::class)->freeForSaleOrderItem((int) $productId, (int) $sourceWarehouseId, !empty($deliveryItem['sale_order_item_id']) ? (int) $deliveryItem['sale_order_item_id'] : null);

                                                                if ((float) $availableStock < $sourceQtyItem) {
                                                                    $fail('Stock tidak mencukupi pada salah satu sumber gudang item delivery order.');
                                                                    return;
                                                                }
                                                            }
                                                        }

                                                        $saleOrderItem = $soItems->get($deliveryItem['sale_order_item_id']);

                                                        if ($saleOrderItem) {
                                                            // Validasi 1: Quantity delivery item tidak boleh lebih besar dari quantity sale order item asli
                                                            if ($deliveryItem['quantity'] > $saleOrderItem->quantity) {
                                                                $productName = $saleOrderItem->product->name ?? "Unknown Product";
                                                                $fail("Quantity untuk item '$productName' ({$deliveryItem['quantity']}) tidak boleh lebih besar dari quantity sale order item ({$saleOrderItem->quantity}).");
                                                                return;
                                                            }

                                                            // Validasi 2: tidak boleh melebihi sisa yang belum terikat DO lain (terkirim/diproses)
                                                            $availableQty = static::availableFor($saleOrderItem, $livewire);
                                                            if ($deliveryItem['quantity'] > $availableQty) {
                                                                $productName = $saleOrderItem->product->name ?? "Unknown Product";
                                                                $fail("Quantity untuk item '$productName' ({$deliveryItem['quantity']}) tidak boleh lebih besar dari sisa quantity yang tersedia ({$availableQty}).");
                                                                return;
                                                            }
                                                        }
                                                    }
                                                }

                                                // Validasi 3: Pastikan tidak ada duplicate sale order item dalam satu delivery order
                                                $saleOrderItemIds = collect($value)->pluck('sale_order_item_id')->filter();
                                                $duplicates = $saleOrderItemIds->duplicates();

                                                if ($duplicates->isNotEmpty()) {
                                                    $fail("Tidak boleh ada duplicate sale order item dalam satu delivery order.");
                                                    return;
                                                }
                                            };
                                        },
                                    ])
                                    ->schema([
                                        Radio::make('options_from')
                                            ->label('Option From')
                                            ->reactive()
                                            ->inlineLabel()
                                            ->hidden() // Hidden: auto-populated from Sales Order
                                            ->options([
                                                '0' => 'None',
                                                '2' => 'From Sales Order Item'
                                            ])->default(function ($get, $set) {
                                                $listSalesOrderId = $get('../../salesOrders');
                                                if (count($listSalesOrderId) > 0) {
                                                    $set('options_from', 2);
                                                    return 2;
                                                }
                                                return 0;
                                            }),
                                        Select::make('sale_order_item_id')
                                            ->label('Sales Order Item')
                                            ->preload()
                                            ->reactive()
                                            ->hidden() // Hidden: auto-populated from Sales Order
                                            ->afterStateUpdated(function ($set, $get, $state, $livewire) {
                                                $saleOrderItem = SaleOrderItem::find($state);
                                                if ($saleOrderItem) {
                                                    $set('product_id', $saleOrderItem->product_id);
                                                    $set('quantity', static::availableFor($saleOrderItem, $livewire));
                                                }
                                            })
                                            ->searchable()
                                            ->relationship('saleOrderItem', 'id', function (Builder $query, $get) {
                                                $listSalesOrderId = $get('../../salesOrders');
                                                $query->with(['saleOrder', 'product'])
                                                    ->when(count($listSalesOrderId) > 0, function (Builder $query) use ($listSalesOrderId) {
                                                        $query->whereIn('sale_order_id', $listSalesOrderId);
                                                    });
                                            })
                                            ->getOptionLabelFromRecordUsing(function (SaleOrderItem $saleOrderItem) {
                                                $remaining = $saleOrderItem->remaining_quantity;
                                                $total = $saleOrderItem->quantity;
                                                return "{$saleOrderItem->saleOrder->so_number} - ({$saleOrderItem->product->sku}) {$saleOrderItem->product->name} [Sisa: {$remaining}/{$total}]";
                                            })
                                            ->nullable(),
                                        Select::make('product_id')
                                            ->label('Product')
                                            ->preload()
                                            ->reactive()
                                            ->searchable()
                                            ->disabled() // Non-editable: auto-populated from Sales Order
                                            ->dehydrated(true) // Required to save disabled values
                                            ->relationship('product', 'id')
                                            ->getOptionLabelFromRecordUsing(function (Product $product) {
                                                return "({$product->sku}) {$product->name}";
                                            })
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Product wajib dipilih',
                                            ]),

                                        TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->reactive()
                                            ->default(0)
                                            ->rules(['required', 'numeric', 'min:1'])
                                            ->validationAttribute('quantity')
                                            ->live()
                                            ->validationMessages([
                                                'required' => 'Quantity wajib diisi',
                                                'numeric' => 'Quantity harus berupa angka',
                                                'min' => 'Quantity minimal 1',
                                            ])
                                            ->afterStateUpdated(function ($state, $set, $get, $component, $livewire) {
                                                $saleOrderItemId = $get('sale_order_item_id');
                                                $optionsFrom = $get('options_from');

                                                if ($optionsFrom == 2 && $saleOrderItemId) {
                                                    $saleOrderItem = SaleOrderItem::find($saleOrderItemId);
                                                    if ($saleOrderItem) {
                                                        $originalQuantity = $saleOrderItem->quantity;
                                                        $remainingQuantity = static::availableFor($saleOrderItem, $livewire);
                                                        $warehouseSources = $saleOrderItem->warehouseAllocations
                                                            ->map(function ($allocation) {
                                                                return [
                                                                    'warehouse_id' => $allocation->warehouse_id,
                                                                    'quantity' => (float) $allocation->quantity,
                                                                    'rak_id' => null,
                                                                ];
                                                            })
                                                            ->values()
                                                            ->toArray();

                                                        if (empty($warehouseSources)) {
                                                            $fallbackWarehouseId = $saleOrderItem->warehouse_id;
                                                            if (!$fallbackWarehouseId) {
                                                                $cabangId = $saleOrderItem->saleOrder?->cabang_id ?? Auth::user()?->cabang_id;
                                                                $fallbackWarehouseId = \App\Models\Warehouse::where('status', 1)
                                                                    ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                                                                    ->value('id');
                                                            }
                                                            if ($fallbackWarehouseId) {
                                                                $warehouseSources = [
                                                                    [
                                                                        'warehouse_id' => $fallbackWarehouseId,
                                                                        'quantity' => (float) $remainingQuantity,
                                                                        'rak_id' => $saleOrderItem->rak_id,
                                                                    ]
                                                                ];
                                                            }
                                                        }

                                                        if (!empty($warehouseSources)) {
                                                            $set('warehouseSources', $warehouseSources);
                                                        }

                                                        // Validasi 1: Tidak boleh lebih besar dari quantity asli sale order item
                                                        if ($state > $originalQuantity) {
                                                            $component->state($originalQuantity);
                                                            \Filament\Notifications\Notification::make()
                                                                ->title('Quantity Validation')
                                                                ->body("Quantity tidak boleh lebih besar dari quantity sale order item asli. Maksimal: {$originalQuantity}")
                                                                ->warning()
                                                                ->send();
                                                            return;
                                                        }

                                                        // Validasi 2: Tidak boleh lebih besar dari remaining quantity
                                                        if ($state > $remainingQuantity) {
                                                            $component->state($remainingQuantity);

                                                            if ($remainingQuantity <= 0) {
                                                                \Filament\Notifications\Notification::make()
                                                                    ->title('Quantity Validation')
                                                                    ->body("Semua quantity untuk item ini sudah dikirim. Sisa quantity: {$remainingQuantity}")
                                                                    ->warning()
                                                                    ->send();
                                                            } else {
                                                                \Filament\Notifications\Notification::make()
                                                                    ->title('Quantity Validation')
                                                                    ->body("Quantity tidak boleh melebihi sisa yang belum dikirim. Maksimal: {$remainingQuantity}")
                                                                    ->warning()
                                                                    ->send();
                                                            }
                                                        }
                                                    }
                                                }
                                            })
                                            ->helperText(function ($get, $livewire) {
                                                $saleOrderItemId = $get('sale_order_item_id');
                                                $optionsFrom = $get('options_from');

                                                if ($optionsFrom == 2 && $saleOrderItemId) {
                                                    $saleOrderItem = SaleOrderItem::find($saleOrderItemId);
                                                    if ($saleOrderItem) {
                                                        $row = app(SaleOrderDeliveryProgress::class)
                                                            ->forItems([$saleOrderItem->id], static::editingDeliveryOrderId($livewire))[$saleOrderItem->id] ?? null;

                                                        if ($row) {
                                                            return "Total SO: {$row['ordered']} | Sudah dikirim: {$row['delivered']} | Sedang diproses di DO lain: {$row['in_process']} | Maks yang bisa dikirim: {$row['available']}";
                                                        }
                                                    }
                                                }

                                                return null;
                                            }),
                                        Textarea::make('reason')
                                            ->label('Reason')
                                            ->nullable(),
                                        Repeater::make('warehouseSources')
                                            ->relationship('warehouseSources')
                                            ->label('Sumber Gudang (Multi-Gudang)')
                                            ->schema([
                                                Select::make('warehouse_id')
                                                    ->label('Gudang Sumber')
                                                    ->reactive()
                                                    ->options(function ($get) {
                                                        // cabang_id ada di root form DO, diisi otomatis dari SO yang dipilih.
                                                        // Path relatif: warehouseSources_item → deliveryOrderItem_item
                                                        //               → deliveryOrderItem_repeater → form_root.
                                                        $cabangId = $get('../../../../cabang_id')
                                                            ?? $get('../../../cabang_id')
                                                            ?? null;

                                                        return WarehouseStockOptions::forProduct(
                                                            $get('../../product_id') ?? $get('../product_id'),
                                                            $get('warehouse_id'),
                                                            true,
                                                            $cabangId ? (int) $cabangId : null,
                                                        );
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->validationMessages([
                                                        'required' => 'Gudang sumber wajib dipilih',
                                                    ]),
                                                Select::make('rak_id')
                                                    ->label('Rak Sumber')
                                                    ->reactive()
                                                    ->options(function ($get) {
                                                        $warehouseId = $get('warehouse_id');
                                                        if (!$warehouseId) {
                                                            return [];
                                                        }

                                                        return Rak::where('warehouse_id', $warehouseId)
                                                            ->get()
                                                            ->mapWithKeys(function ($rak) {
                                                                return [$rak->id => filled($rak->code) ? "({$rak->code}) {$rak->name}" : ($rak->name ?? '-')];
                                                            });
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->nullable(),
                                                TextInput::make('quantity')
                                                    ->label('Qty Sumber')
                                                    ->reactive()
                                                    ->numeric()
                                                    ->suffix(function ($get) {
                                                        $productId = $get('../../product_id') ?? $get('../product_id');
                                                        $warehouseId = $get('warehouse_id');
                                                        $rakId = $get('rak_id');

                                                        if (!$productId || !$warehouseId) {
                                                            return null;
                                                        }

                                                        $stockQuery = InventoryStock::where('product_id', $productId)
                                                            ->where('warehouse_id', $warehouseId);

                                                        if ($rakId) {
                                                            $stockQuery->where('rak_id', $rakId);
                                                        }

                                                        $available = app(\App\Services\StockAvailability::class)->freeForSaleOrderItem((int) $productId, (int) $warehouseId, ($soItemId = $get('../../sale_order_item_id') ?? $get('../sale_order_item_id')) ? (int) $soItemId : null, $rakId ? (int) $rakId : null);
                                                        if ($available <= 0) {
                                                            return '🚨 HABIS';
                                                        }
                                                        if ($available < 10) {
                                                            return '⚠️ ' . number_format($available, 0, ',', '.');
                                                        }

                                                        return '✅ ' . number_format($available, 0, ',', '.');
                                                    })
                                                    ->helperText(function ($get) {
                                                        $productId = $get('../../product_id') ?? $get('../product_id');
                                                        $warehouseId = $get('warehouse_id');
                                                        $rakId = $get('rak_id');
                                                        $qty = (float) ($get('quantity') ?? 0);

                                                        if (!$productId || !$warehouseId) {
                                                            return 'Pilih produk dan gudang sumber untuk melihat stok bebas.';
                                                        }

                                                        $stockQuery = InventoryStock::where('product_id', $productId)
                                                            ->where('warehouse_id', $warehouseId);

                                                        if ($rakId) {
                                                            $stockQuery->where('rak_id', $rakId);
                                                        }

                                                        $available = app(\App\Services\StockAvailability::class)->freeForSaleOrderItem((int) $productId, (int) $warehouseId, ($soItemId = $get('../../sale_order_item_id') ?? $get('../sale_order_item_id')) ? (int) $soItemId : null, $rakId ? (int) $rakId : null);
                                                        if ($qty > 0 && $qty > $available) {
                                                            return 'Qty melebihi stok bebas: ' . number_format($available, 0, ',', '.');
                                                        }

                                                        return 'Stok bebas: ' . number_format($available, 0, ',', '.');
                                                    })
                                                    ->required(),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull()
                                            ->collapsed()
                                            ->helperText('Pilih gudang sumber. Jika SO sudah memiliki alokasi gudang, akan diisi otomatis. Anda tetap dapat mengubahnya jika diperlukan.'),
                                    ])
                                    ->visible(function ($get, $context) {
                                        // Show deliveryOrderItem repeater when editing OR when creating (to enable relationship saving)
                                        return $context === 'edit' || !empty($get('salesOrders'));
                                    })
                            ])
                    ])
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Delivery Order Details')
                    ->schema([
                        TextEntry::make('do_number')->label('DO Number'),
                        TextEntry::make('delivery_date')->dateTime(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn ($state) => DeliveryOrder::statusLabel($state))
                            ->color(fn ($state) => DeliveryOrder::statusColor($state))
                            ->badge(),
                        TextEntry::make('shipping_method')
                            ->label('Metode Pengiriman')
                            ->getStateUsing(function ($record) {
                                $deliverySchedule = $record->deliverySchedules()->with('driver')->orderByDesc('scheduled_date')->orderByDesc('id')->first();
                                return $deliverySchedule?->delivery_method_label ?? '-';
                            })
                            ->placeholder('-'),
                        TextEntry::make('notes'),
                        TextEntry::make('cabang.nama')->label('Cabang'),
                    ])->columns(2),
                // customer info derived from the first linked sale order
                Section::make('Customer Information')
                    ->schema([
                        TextEntry::make('salesOrders.0.customer.name')->label('Name'),
                        TextEntry::make('salesOrders.0.customer.perusahaan')->label('Company'),
                        TextEntry::make('salesOrders.0.customer.address')->label('Address'),
                        TextEntry::make('salesOrders.0.customer.phone')->label('Phone'),
                        TextEntry::make('salesOrders.0.customer.email')->label('Email'),
                    ])->columns(2),
                Section::make('Sales Orders')
                    ->schema([
                        RepeatableEntry::make('salesOrders')
                            ->label('')
                            ->schema([
                                TextEntry::make('so_number')->label('SO Number'),
                                TextEntry::make('createdBy.name')->label('Sales'),
                                TextEntry::make('customer.perusahaan')->label('Customer')->placeholder('-'),
                                TextEntry::make('status')
                                    ->label('Status SO')
                                    ->formatStateUsing(fn ($state) => SaleOrder::statusLabel($state))
                                    ->color(fn ($state) => SaleOrder::statusColor($state))
                                    ->badge()
                                    ->placeholder('-'),
                            ])->columns(4),
                    ]),
                Section::make('Delivery Order Items')
                    ->schema([
                        RepeatableEntry::make('deliveryOrderItem')
                            ->label('')
                            ->schema([
                                TextEntry::make('product.name')->label('Product')
                                    ->formatStateUsing(function ($state, $record) {
                                        return "({$record->product->sku}) {$state}";
                                    }),
                                TextEntry::make('quantity')
                                    ->label('Quantity')
                                    ->formatStateUsing(function ($state, $record) {
                                        $unit = $record->product->uom->abbreviation ?? $record->product->uom->name ?? null;
                                        return $state . ($unit ? " {$unit}" : '');
                                    }),
                                TextEntry::make('product.uom.name')->label('Satuan')->placeholder(function ($record) {
                                    return $record->product->uom->abbreviation ?? $record->product->uom->name ?? '-';
                                }),
                                TextEntry::make('reason'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->formatStateUsing(fn ($state) => \App\Models\DeliveryOrderItem::statusLabel($state))
                                    ->color(fn ($state) => \App\Models\DeliveryOrderItem::statusColor($state))
                                    ->badge(),
                            ])->columns(4)
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Journal Entries')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('view_journal_entries')
                            ->label('View All Journal Entries')
                            ->icon('heroicon-o-document-text')
                            ->color('primary')
                            ->url(function ($record) {
                                // Redirect to JournalEntryResource with filter for this delivery order
                                $sourceType = urlencode(\App\Models\DeliveryOrder::class);
                                $sourceId = $record->id;

                                return "/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][source_id]={$sourceId}";
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
                                TextEntry::make('debit')->rupiah()->label('Debit')->color('success'),
                                TextEntry::make('credit')->rupiah()->label('Credit')->color('danger'),
                                TextEntry::make('description')->label('Description'),
                                TextEntry::make('journal_type')->badge()->label('Type'),
                            ])->columns(4),
                    ])
                    ->columns(1)
                    ->visible(function ($record) {
                        return $record->journalEntries()->exists();
                    }),

                Section::make('Status Konfirmasi Gudang')
                    ->description('Setiap kartu mewakili satu request item pada satu gudang sumber. DO di-approve otomatis jika semua request dikonfirmasi; DO ditolak otomatis jika ada satu request yang ditolak.')
                    ->collapsible() // UX: collapsible section
                    ->collapsed() // Starts collapsed by default
                    ->schema([
                        RepeatableEntry::make('warehouseConfirmations')
                            ->label('')
                            ->schema([
                                // Show GROUP STRUCTURE only (not raw data details):
                                TextEntry::make('warehouseConfirmationItems.0.warehouse.name')
                                    ->label('Gudang')
                                    ->placeholder('-')
                                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                TextEntry::make('status')
                                    ->label('Status Konfirmasi Gudang')
                                    ->formatStateUsing(fn($state) => match (strtolower((string) $state)) {
                                        'confirmed'         => 'Terkonfirmasi',
                                        'rejected'          => 'Ditolak',
                                        'partial_confirmed' => 'Sebagian Dikonfirmasi',
                                        'request'           => 'Menunggu Konfirmasi',
                                        default             => ucfirst(str_replace('_', ' ', (string) $state)),
                                    })
                                    ->badge()
                                    ->color(fn($state) => match (strtolower((string) $state)) {
                                        'confirmed'         => 'success',
                                        'rejected'          => 'danger',
                                        'partial_confirmed' => 'warning',
                                        'request'           => 'info',
                                        default             => 'gray',
                                    }),
                                TextEntry::make('warehouseConfirmationItems')
                                    ->label('Jumlah Item')
                                    ->getStateUsing(fn($record) => $record->warehouseConfirmationItems->count())
                                    ->placeholder('-'),
                            ])->columns(3)
                            ->columnSpanFull()
                            ->grid(1) // One card per row
                    ])
                    ->visible(fn($record) => $record->warehouseConfirmations()->exists()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('do_number')
                    ->label('Nomor DO')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_names')
                    ->label('Customer')
                    ->getStateUsing(function ($record) {
                        $names = collect();
                        foreach ($record->salesOrders as $so) {
                            if ($so->customer) {
                                $names->push($so->customer->perusahaan ?? $so->customer->name ?? '');
                            }
                        }
                        return $names->unique()->filter()->implode(', ') ?: '-';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('salesOrders.customer', function (Builder $query) use ($search) {
                            $query->where('perusahaan', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                    })
                    ->wrap(),
                TextColumn::make('delivery_date')
                    ->label('Tanggal Pengiriman')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => DeliveryOrder::statusLabel($state))
                    ->color(fn ($state) => DeliveryOrder::statusColor($state))
                    ->badge(),
                TextColumn::make('suratJalan')
                    ->label('Surat Jalan')
                    ->getStateUsing(function ($record) {
                        if ($record->suratJalan->isNotEmpty()) {
                            $suratJalan = $record->suratJalan->first();
                            return $suratJalan->sj_number;
                        }
                        return 'Tidak Ada';
                    })
                    ->badge()
                    ->color(function ($state) {
                        return $state === 'Ada' ? 'success' : 'warning';
                    })
                    ->tooltip(function ($record) {
                        if ($record->suratJalan->isNotEmpty()) {
                            $suratJalan = $record->suratJalan->first();
                            if ($suratJalan) {
                                return "Surat Jalan: {$suratJalan->sj_number}\nStatus: {$suratJalan->status}";
                            }
                        }
                        return 'Delivery Order belum memiliki Surat Jalan. Surat Jalan sekarang hanya dipakai sebagai dokumen DO, bukan syarat approval atau pengiriman.';
                    }),
                TextColumn::make('driver.name')
                    ->label('Driver')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vehicle')
                    ->label('Kendaraan')
                    ->formatStateUsing(function ($state) {
                        return $state->plate . ' - ' . $state->type;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('salesOrders.so_number')
                    ->label('Sales Orders')
                    ->badge()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('salesOrders.createdBy.name')
                    ->label('Sales')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(function ($state) {
                        return $state ?? 'System';
                    })
                    ->visible(function () {
                        // Hanya tampilkan kolom Sales jika user adalah Super Sales, Sales Manager atau Admin
                        $user = Auth::user();
                        return $user->hasRole(['Super Sales', 'Sales Manager', 'Super Admin', 'Owner', 'Admin']);
                    }),
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
                    ->options(DeliveryOrder::STATUS_LABELS),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                // Eager load relationships to prevent N+1 queries
                $query->with(['suratJalan', 'salesOrders']);

                $user = Auth::user();

                // Jika user adalah Super Sales, Sales Manager, Admin, Owner - bisa lihat semua
                if ($user->hasRole(['Super Sales', 'Sales Manager', 'Super Admin', 'Owner', 'Admin'])) {
                    return $query;
                }

                // Jika user adalah Sales - hanya bisa lihat delivery order dari sale order yang dia buat
                if ($user->hasRole('Sales')) {
                    return $query->whereHas('salesOrders', function (Builder $subQuery) use ($user) {
                        $subQuery->where('created_by', $user->id);
                    });
                }

                return $query;
            })
            ->headerActions([
                Action::make('rekap_driver')
                    ->label('Rekap per Driver')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->form([
                        Select::make('driver_ids')
                            ->label('Driver')
                            ->multiple()
                            ->options(fn() => Driver::orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->placeholder('Pilih satu atau lebih driver...'),
                        DatePicker::make('date_from')
                            ->label('Dari Tanggal')
                            ->displayFormat('d/m/Y')
                            ->nullable(),
                        DatePicker::make('date_to')
                            ->label('Sampai Tanggal')
                            ->displayFormat('d/m/Y')
                            ->nullable(),
                        Radio::make('format')
                            ->label('Format Export')
                            ->options(['excel' => 'Excel (.xlsx)', 'pdf' => 'PDF'])
                            ->default('excel')
                            ->inline(),
                    ])
                    ->action(function (array $data) {
                        $driverIds = $data['driver_ids'];
                        $dateFrom  = $data['date_from']  ?? null;
                        $dateTo    = $data['date_to']    ?? null;
                        $format    = $data['format']     ?? 'excel';

                        if ($format === 'excel') {
                            return Excel::download(
                                new DeliveryOrderRecapExport($driverIds, $dateFrom, $dateTo),
                                'rekap-do-driver-' . now()->format('Ymd-His') . '.xlsx'
                            );
                        }

                        // PDF
                        $orders = DeliveryOrder::with(['driver', 'salesOrders.customer', 'deliveryOrderItem.product'])
                            ->whereIn('driver_id', $driverIds)
                            ->when($dateFrom, fn($q) => $q->whereDate('delivery_date', '>=', $dateFrom))
                            ->when($dateTo,   fn($q) => $q->whereDate('delivery_date', '<=', $dateTo))
                            ->orderBy('driver_id')
                            ->orderBy('delivery_date')
                            ->get();

                        $driverGroups = $orders->groupBy(fn($do) => $do->driver->name ?? 'Tanpa Driver');
                        $drivers      = Driver::whereIn('id', $driverIds)->get();

                        $pdf = Pdf::loadView('pdf.delivery-order-recap', [
                            'driverGroups' => $driverGroups,
                            'drivers'      => $drivers,
                            'dateFrom'     => $dateFrom,
                            'dateTo'       => $dateTo,
                        ])->setPaper('a4', 'landscape');

                        $filename = 'rekap-do-driver-' . now()->format('Ymd-His') . '.pdf';

                        return response()->streamDownload(
                            fn() => print($pdf->output()),
                            $filename
                        );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                    // G-01: For draft DOs, guide user to View page where the
                    // canonical "Request Stock ke Gudang" action lives (H4 flow).
                    // "Request Approve" from list is intentionally removed for draft to
                    // avoid dual-flow confusion. Request Approve still available on
                    // request_stock status DOs (waiting for finance approval after WC confirmed).
                    Action::make('request_approve')
                        ->label('Ajukan Persetujuan')
                        ->requiresConfirmation()
                        ->modalHeading('Ajukan Persetujuan Delivery Order')
                        ->modalDescription(fn ($record) => "Ajukan persetujuan pengiriman untuk DO {$record->delivery_order_number}. DO akan diteruskan ke Manajer Logistik/Finance.")
                        ->modalSubmitActionLabel('Ya, Ajukan')
                        ->color('success')
                        ->icon('heroicon-o-arrow-uturn-up')
                        ->visible(function ($record) {
                            // Only visible for request_stock (after WC confirmed flow),
                            // NOT for draft (use "Request Stock" on view page instead).
                            return Auth::user()->hasPermissionTo('request delivery order') && $record->status == 'request_stock';
                        })
                        ->action(function ($record) {
                            $deliveryOrderService = app(DeliveryOrderService::class);
                            $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'request_approve');
                            HelperController::sendNotification(isSuccess: true, title: "Informasi", message: "Delivery Order telah diajukan untuk persetujuan. Proses selanjutnya: Persetujuan oleh Manajer Logistik/Finance.");
                        }),
                    Action::make('request_close')
                        ->label('Minta Tutup')
                        ->requiresConfirmation()
                        ->modalHeading('Minta Penutupan Delivery Order')
                        ->modalDescription(fn ($record) => "Ajukan permintaan penutupan DO {$record->delivery_order_number}. Pengiriman akan dihentikan dan menunggu konfirmasi.")
                        ->modalSubmitActionLabel('Ya, Minta Tutup')
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request delivery order') &&
                                in_array($record->status, ['draft', 'request_approve', 'request_close']);
                        })
                        ->action(function ($record) {
                            $deliveryOrderService = app(DeliveryOrderService::class);
                            $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'request_close');
                            HelperController::sendNotification(isSuccess: true, title: "Informasi", message: "Permintaan penutupan Delivery Order telah diajukan. Proses selanjutnya: Konfirmasi penutupan oleh Manajer Logistik.");
                        }),
                    Action::make('approve')
                        ->label('Konfirmasi Dana Diterima')
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi: Apakah Dana Sudah Diterima?')
                        ->modalDescription('Dengan mengkonfirmasi ini, Anda menyatakan bahwa pembayaran untuk Delivery Order ini sudah diterima dan barang siap dijadwalkan untuk pengiriman.')
                        ->modalSubmitActionLabel('Ya, Dana Sudah Diterima')
                        ->color('success')
                        ->icon('heroicon-o-check-badge')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response delivery order') &&
                                $record->status == 'request_approve';
                        })
                        ->form([
                            Textarea::make('comments')
                                ->label('Catatan (opsional)')
                                ->placeholder('Tambahkan catatan jika diperlukan...')
                                ->nullable()
                        ])
                        ->action(function ($record, array $data) {
                            try {
                                $deliveryOrderService = app(DeliveryOrderService::class);
                                $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'approved', comments: $data['comments'] ?? null, action: 'approved');

                                HelperController::sendNotification(isSuccess: true, title: "Dana Dikonfirmasi", message: "Pembayaran Delivery Order telah dikonfirmasi diterima. Proses selanjutnya: jadwalkan pengiriman pada Delivery Schedule.");
                            } catch (\Throwable $exception) {
                                \App\Support\ProcurementFailureNotifier::danger(
                                    'Gagal Mengonfirmasi Dana',
                                    $exception,
                                    'Konfirmasi dana Delivery Order belum berhasil diproses. Silakan coba lagi.'
                                );
                            }
                        }),
                    Action::make('closed')
                        ->label('Tutup DO')
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Penutupan DO')
                        ->modalDescription(fn ($record) => "Menutup DO {$record->delivery_order_number} akan memfinalisasi status pengiriman dan data akan dikunci.")
                        ->modalSubmitActionLabel('Ya, Tutup DO')
                        ->color('warning')
                        ->icon('heroicon-o-x-circle')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response delivery order') &&
                                $record->status == 'request_close';
                        })
                        ->action(function ($record) {
                            $deliveryOrderService = app(DeliveryOrderService::class);
                            $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'closed');
                            HelperController::sendNotification(isSuccess: true, title: "Informasi", message: "Delivery Order telah ditutup. Proses selanjutnya: Tim Finance perlu memastikan Invoice telah diterbitkan dan diselesaikan untuk Delivery Order ini.");
                        }),
                    Action::make('reject')
                        ->label('Tolak DO')
                        ->requiresConfirmation()
                        ->modalHeading('Tolak Delivery Order')
                        ->modalDescription(fn ($record) => "Menolak DO {$record->delivery_order_number} akan mengembalikan status ke tim operasional untuk perbaikan.")
                        ->modalSubmitActionLabel('Ya, Tolak')
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response delivery order') && ($record->status == 'request_approve');
                        })
                        ->form([
                            Textarea::make('comments')
                                ->label('Alasan Penolakan')
                                ->placeholder('Tuliskan alasan penolakan...')
                                ->required()
                        ])
                        ->action(function ($record, array $data) {
                            $deliveryOrderService = app(DeliveryOrderService::class);
                            $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'reject', comments: $data['comments'], action: 'rejected');

                            // DeliveryOrderApprovalLog::create([
                            //     'delivery_order_id' => $record->id,
                            //     'user_id' => Auth::id(),
                            //     'action' => 'rejected',
                            //     'comments' => $data['comments'],
                            //     'approved_at' => now(),
                            // ]);

                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Delivery Order telah ditolak. Proses selanjutnya: Tim Logistik perlu memperbaiki data Delivery Order sesuai alasan penolakan dan mengajukan kembali untuk persetujuan.");
                        }),
                    Action::make('pdf_delivery_order')
                        ->label('Preview / Download PDF')
                        ->color('info')
                        ->icon('heroicon-o-document-arrow-down')
                        ->visible(fn ($record) => in_array($record->status, ['approved', 'completed', 'confirmed', 'received', 'sent']))
                        ->url(fn ($record) => route('pdf-stream', ['type' => 'delivery-order', 'id' => $record->id]))
                        ->openUrlInNewTab(),
                    Action::make('checker_edit_quantity')
                        ->label('Checker Edit Qty')
                        ->color('warning')
                        ->icon('heroicon-o-pencil-square')
                        ->visible(function ($record) {
                            // Hanya tampil untuk status approved dan user dengan role checker atau admin
                            return ($record->status == 'approved' || $record->status == 'confirmed') &&
                                Auth::user()->hasRole(['Checker', 'Super Admin', 'Owner', 'Admin']);
                        })
                        ->form([
                            Fieldset::make('Edit Quantity untuk Checker')
                                ->schema([
                                    Repeater::make('delivery_items')
                                        ->label('Delivery Order Items')
                                        ->schema([
                                            TextInput::make('product_name')
                                                ->label('Product')
                                                ->disabled()
                                                ->columnSpan(2),
                                            TextInput::make('original_quantity')
                                                ->label('Qty Asli')
                                                ->disabled()
                                                ->numeric(),
                                            TextInput::make('current_quantity')
                                                ->label('Qty Saat Ini')
                                                ->disabled()
                                                ->numeric(),
                                            TextInput::make('new_quantity')
                                                ->label('Qty Baru')
                                                ->numeric()
                                                ->required()
                                                ->minValue(0)
                                                ->default(function ($get) {
                                                    return $get('current_quantity') ?? 0;
                                                })
                                                ->afterStateUpdated(function ($state, $set, $get) {
                                                    $originalQty = $get('original_quantity');
                                                    if ($state > $originalQty) {
                                                        $set('new_quantity', $originalQty);
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Quantity Validation')
                                                            ->body("Quantity tidak boleh melebihi quantity asli: {$originalQty}")
                                                            ->warning()
                                                            ->send();
                                                    }
                                                }),
                                        ])
                                        ->columns(2)
                                        ->columnSpanFull()
                                        ->defaultItems(0)
                                        ->itemLabel('Delivery Item')
                                        ->addable(false)
                                        ->deletable(false)
                                        ->mutateDehydratedStateUsing(function ($state) {
                                            // Pastikan semua item memiliki key yang diperlukan
                                            return collect($state)->map(function ($item) {
                                                return [
                                                    'product_name' => $item['product_name'] ?? '',
                                                    'original_quantity' => $item['original_quantity'] ?? 0,
                                                    'current_quantity' => $item['current_quantity'] ?? 0,
                                                    'new_quantity' => $item['new_quantity'] ?? 0,
                                                    'delivery_order_item_id' => $item['delivery_order_item_id'] ?? null,
                                                ];
                                            })->filter(function ($item) {
                                                // Filter hanya item yang diubah quantity-nya
                                                return ($item['new_quantity'] ?? 0) != ($item['current_quantity'] ?? 0);
                                            })->values()->toArray();
                                        })
                                        ->default(function ($record) {
                                            $items = [];
                                            foreach ($record->deliveryOrderItem as $item) {
                                                $items[] = [
                                                    'product_name' => $item->product->name . ' (' . $item->product->sku . ')',
                                                    'original_quantity' => $item->quantity, // Quantity asli dari sale order item
                                                    'current_quantity' => $item->quantity, // Quantity saat ini di delivery order
                                                    'new_quantity' => $item->quantity, // Default sama dengan current
                                                    'delivery_order_item_id' => $item->id,
                                                ];
                                            }
                                            return $items;
                                        }),
                                    Textarea::make('checker_notes')
                                        ->label('Catatan Checker')
                                        ->placeholder('Berikan alasan perubahan quantity...')
                                        ->nullable(),
                                ])
                        ])
                        ->action(function ($record, array $data) {
                            $deliveryOrderService = app(DeliveryOrderService::class);

                            // Pastikan delivery_items ada dan merupakan array
                            $deliveryItems = $data['delivery_items'] ?? [];

                            // Update quantity untuk setiap item yang diubah
                            foreach ($deliveryItems as $itemData) {
                                // Pastikan semua key yang diperlukan ada
                                $deliveryOrderItemId = $itemData['delivery_order_item_id'] ?? null;
                                $newQuantity = $itemData['new_quantity'] ?? 0;
                                $currentQuantity = $itemData['current_quantity'] ?? 0;

                                if ($deliveryOrderItemId && $newQuantity != $currentQuantity) {
                                    $deliveryItem = $record->deliveryOrderItem()->find($deliveryOrderItemId);
                                    if ($deliveryItem) {
                                        $deliveryItem->update([
                                            'quantity' => $newQuantity
                                        ]);

                                        // Progres SO dihitung ulang oleh SaleOrderDeliveryProgress (hook item DO);
                                        // tidak ada penulis delivered_quantity lain di sini.
                                        app(SaleOrderDeliveryProgress::class)->syncForDeliveryOrder($record);

                                        // Log perubahan quantity
                                        \App\Models\DeliveryOrderLog::create([
                                            'delivery_order_id' => $record->id,
                                            'status' => $record->status,
                                            'confirmed_by' => Auth::id(),
                                            'action' => 'quantity_updated_by_checker',
                                            'comments' => 'Old: ' . $currentQuantity . ', New: ' . $newQuantity . '. ' . ($data['checker_notes'] ?? ''),
                                            'user_id' => Auth::id(),
                                            'old_value' => (string)$currentQuantity,
                                            'new_value' => (string)$newQuantity,
                                            'notes' => $data['checker_notes'] ?? null,
                                        ]);
                                    }
                                }
                            }

                            HelperController::sendNotification(
                                isSuccess: true,
                                title: "Quantity Updated",
                                message: "Quantity delivery order telah diperbarui oleh checker"
                            );
                        }),
                    \App\Filament\Support\DeliveryOrderActions::dispatch(Action::make('dispatch')),
                    \App\Filament\Support\DeliveryOrderActions::receive(Action::make('receive')),
                    \App\Filament\Support\DeliveryOrderActions::complete(Action::make('complete_delivery')),
                    \App\Filament\Support\DeliveryOrderActions::cancel(Action::make('cancel_delivery_order')),
                    \App\Filament\Support\DeliveryOrderActions::failed(
                        Action::make('mark_delivery_failed')
                            ->label('Pengiriman Gagal')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Tandai Pengiriman Gagal')
                            ->modalDescription('Apakah Anda yakin pengiriman ini gagal? DO akan ditandai sebagai Pengiriman Gagal dan dapat diprioritaskan untuk pengiriman berikutnya.')
                            ->modalSubmitActionLabel('Ya, Tandai Gagal')
                            ->visible(function ($record) {
                                return Auth::user()->hasPermissionTo('response delivery order') &&
                                    in_array($record->status, ['sent', 'approved']);
                            })
                    ),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordClasses(fn($record) => match ($record->status) {
                'draft' => 'bg-gray-100',
                'request_stock' => 'bg-yellow-100',
                'partial' => 'bg-yellow-100',
                'sent' => 'bg-blue-100',
                'received' => 'bg-blue-100',
                'request_approve' => 'bg-blue-100',
                'approved' => 'bg-blue-100',
                'completed' => 'bg-green-100',
                'request_close' => 'bg-yellow-100',
                'closed' => 'bg-red-100',
                'reject' => 'bg-red-100',
                'delivery_failed' => 'bg-red-100',
                'supplier' => 'bg-yellow-100',
                default => '',
            })
            ->description(new \Illuminate\Support\HtmlString(
                '<style>
                    .fi-ta-header:has(.do-legend){display:block!important;width:100%}
                    .fi-ta-description:has(.do-legend){display:block!important;width:100%;margin-bottom:16px}
                    .do-legend{width:100%;min-width:100%;max-width:none;box-sizing:border-box;display:block}
                    .do-legend+.fi-ta-header,.fi-ta-description+.fi-ta-header{margin-top:16px!important}
                    .fi-ta-description .do-legend{margin-bottom:0}
                </style>' .
                '<div class="do-legend mb-4" style="width:100%;min-width:100%;max-width:none;box-sizing:border-box;margin-bottom:16px;">' .
                '<details class="group bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm transition-all duration-200 w-full" style="width:100%;box-sizing:border-box;border:1px solid #edf2f7;border-radius:12px;padding:16px;background-color:#ffffff;margin-bottom:16px;">' .
                    '<summary class="flex justify-between items-center cursor-pointer font-semibold text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-primary-400" style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;font-weight:600;color:#374151;">' .
                        '<span class="flex items-center gap-2" style="display:flex;align-items:center;gap:8px;">' .
                        '<svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;color:#3b82f6;">' .
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />' .
                        '</svg>' .
                        'Panduan Delivery Order' .
                        '</span>' .
                        '<span class="transition group-open:rotate-180">' .
                        '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:20px;height:20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>' .
                        '</span>' .
                    '</summary>' .
                    '<div class="mt-3 text-sm text-gray-600 dark:text-gray-400 space-y-2 pl-7 border-l-2 border-primary-500/30" style="margin-top:12px;font-size:14px;color:#4b5563;padding-left:28px;border-left:2px solid rgba(59,130,246,0.3);">' .
                    '<ul class="list-disc pl-0" style="list-style:none;padding-left:0;">' .
                    '<li><strong>Apa ini:</strong> Delivery Order adalah dokumen pengiriman barang dari penjualan.</li>' .
                    '<li><strong>Flow:</strong> Draft → Request Stock → Request Approve → Approved → Sent → Received → Completed.</li>' .
                    '<li><strong>Checker:</strong> User dengan role Checker dapat edit quantity setelah approved.</li>' .
                    '<li><strong>PDF:</strong> Download PDF tersedia setelah status approved atau completed.</li>' .
                    '</ul>' .
                    '</div>' .
                '</details>' .
                '<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm w-full" style="width:100%;box-sizing:border-box;border:1px solid #edf2f7;border-radius:12px;padding:16px;background-color:#ffffff;">' .
                    '<h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-2" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280;margin-bottom:12px;display:flex;align-items:center;gap:8px;">' .
                    '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;">' .
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />' .
                    '</svg>' .
                    'Legenda Warna Status Baris Data' .
                    '</h4>' .
                    '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3" style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px;">' .
                    '<!-- Abu (Draft) -->' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: #f9fafb; border: 1px solid #e5e7eb;">' .
                    '<div style="width: 16px; height: 16px; border-radius: 4px; border: 1.5px solid #9ca3af; background-color: #ffffff; box-shadow: 0 1px 3px rgba(156, 163, 175, 0.4); flex-shrink: 0;"></div>' .
                    '<div class="leading-tight">' .
                    '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #4b5563;">Abu (Draft)</span>' .
                    '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Baru dibuat</span>' .
                    '</div>' .
                    '</div>' .
                    '<!-- Kuning (Request Stock) -->' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(254, 243, 199, 0.4); border: 1px solid rgba(253, 230, 138, 0.8);">' .
                    '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #eab308; box-shadow: 0 1px 3px rgba(234, 179, 8, 0.4); flex-shrink: 0;"></div>' .
                    '<div class="leading-tight">' .
                    '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #854d0e;">Kuning (Request Stock)</span>' .
                    '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Menunggu stock</span>' .
                    '</div>' .
                    '</div>' .
                    '<!-- Biru (Sent/Approved) -->' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(219, 234, 254, 0.4); border: 1px solid rgba(191, 219, 254, 0.8);">' .
                    '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #3b82f6; box-shadow: 0 1px 3px rgba(59, 130, 246, 0.4); flex-shrink: 0;"></div>' .
                    '<div class="leading-tight">' .
                    '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #1e40af;">Biru (Sent/Approved)</span>' .
                    '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Disetujui/dikirim</span>' .
                    '</div>' .
                    '</div>' .
                    '<!-- Hijau (Completed) -->' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(220, 252, 231, 0.4); border: 1px solid rgba(187, 247, 208, 0.8);">' .
                    '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #22c55e; box-shadow: 0 1px 3px rgba(34, 197, 94, 0.4); flex-shrink: 0;"></div>' .
                    '<div class="leading-tight">' .
                    '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #166534;">Hijau (Completed)</span>' .
                    '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Selesai diterima</span>' .
                    '</div>' .
                    '</div>' .
                    '<!-- Merah (Closed/Reject) -->' .
                    '<div class="flex items-center gap-3 p-2 rounded-lg" style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 8px; background-color: rgba(254, 226, 226, 0.4); border: 1px solid rgba(254, 202, 202, 0.8);">' .
                    '<div style="width: 16px; height: 16px; border-radius: 4px; background-color: #ef4444; box-shadow: 0 1px 3px rgba(239, 68, 68, 0.4); flex-shrink: 0;"></div>' .
                    '<div class="leading-tight">' .
                    '<span class="block text-xs font-bold" style="display: block; font-size: 11px; font-weight: 700; color: #991b1b;">Merah (Closed/Reject)</span>' .
                    '<span class="text-[10px] text-gray-500" style="font-size: 9px; color: #6b7280;">Ditolak/ditutup</span>' .
                    '</div>' .
                    '</div>' .
                    '</div>' .
                '</div>'
            ));
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\DeliveryOrderResource\RelationManagers\ApprovalLogsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'salesOrders.customer',
                'salesOrders.createdBy',
                'driver',
                'vehicle',
                'journalEntries.coa',
                'suratJalan',
            ])
            ->withCount('journalEntries')
            ->orderBy('delivery_date', 'DESC');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryOrders::route('/'),
            'create' => Pages\CreateDeliveryOrder::route('/create'),
            'view' => ViewDeliveryOrder::route('/{record}'),
            'edit' => Pages\EditDeliveryOrder::route('/{record}/edit'),
        ];
    }
}
