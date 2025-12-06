<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryOrderResource\Pages;
use App\Filament\Resources\DeliveryOrderResource\Pages\ViewDeliveryOrder;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderApprovalLog;
use App\Models\Product;
use App\Models\PurchaseReceiptItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Services\DeliveryOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
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

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Delivery Order';

    // Position Delivery Order after Penjualan groups
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Delivery Order')
                    ->schema([
                        TextInput::make('do_number')
                            ->label('Develiry Order Number')
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
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(Cabang::all()->mapWithKeys(function ($cabang) {
                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                            }))
                            ->visible(fn () => in_array('all', Auth::user()?->manage_type ?? []))
                            ->default(fn () => in_array('all', Auth::user()?->manage_type ?? []) ? null : Auth::user()?->cabang_id)
                            ->required()
                            ->helperText('Pilih cabang untuk delivery order ini'),
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
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $listSaleOrder = SaleOrder::whereIn('id', $state)->get();
                                $selectedItems = [];

                                foreach ($listSaleOrder as $saleOrder) {
                                    foreach ($saleOrder->saleOrderItem as $saleOrderItem) {
                                        $remainingQty = $saleOrderItem->remaining_quantity;
                                        // Only add items that still have remaining quantity
                                        if ($remainingQty > 0) {
                                            // Add to selected_items for checkbox interface
                                            $selectedItems[] = [
                                                'selected' => false, // Default unchecked
                                                'product_name' => "({$saleOrderItem->product->sku}) {$saleOrderItem->product->name}",
                                                'remaining_qty' => $remainingQty,
                                                'quantity' => 0, // Default 0, will be set when checked
                                                'sale_order_item_id' => $saleOrderItem->id,
                                                'product_id' => $saleOrderItem->product_id,
                                            ];
                                        }
                                    }
                                }

                                $set('selected_items', $selectedItems);
                            })
                            ->options(function () {
                                return SaleOrder::where('tipe_pengiriman', 'Kirim Langsung')
                                    ->whereIn('status', ['confirmed', 'completed'])
                                    ->whereNotNull('warehouse_confirmed_at')
                                    ->pluck('so_number', 'id');
                            })
                            ->multiple()
                            ->required()
                            ->helperText('Hanya Sales Order yang sudah dikonfirmasi warehouse yang dapat dipilih untuk membuat Delivery Order.'),
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
                        Select::make('driver_id')
                            ->label('Driver')
                            ->searchable()
                            ->preload()
                            ->validationMessages([
                                'required' => 'Driver tidak boleh kosong',
                            ])
                            ->relationship('driver', 'name')
                            ->required(),
                        Select::make('vehicle_id')
                            ->label('Vehicle')
                            ->preload()
                            ->searchable()
                            ->validationMessages([
                                'required' => 'Vehicle tidak boleh kosong',
                            ])
                            ->relationship('vehicle', 'plate')
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->nullable(),
                        TextInput::make('additional_cost')
                            ->label('Biaya Tambahan')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01)
                            ->helperText('Biaya tambahan seperti ongkos kirim, asuransi, dll.')
                            ->nullable(),
                        Textarea::make('additional_cost_description')
                            ->label('Deskripsi Biaya Tambahan')
                            ->nullable()
                            ->rows(3)
                            ->helperText('Jelaskan detail biaya tambahan yang dikenakan'),

                        // Section untuk memilih item dari sales order
                        Fieldset::make('Pilih Barang untuk Dikirim')
                            ->schema([
                                Repeater::make('selected_items')
                                    ->label('')
                                    ->reactive()
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->defaultItems(0)
                                    ->mutateRelationshipDataBeforeCreateUsing(function ($data) {
                                        // Debug: Log relationship data before create
                                        \Illuminate\Support\Facades\Log::info('DeliveryOrderItem relationship data before create:', $data);
                                        return $data;
                                    })
                                    ->mutateRelationshipDataBeforeSaveUsing(function ($data) {
                                        // Debug: Log relationship data before save
                                        \Illuminate\Support\Facades\Log::info('DeliveryOrderItem relationship data before save:', $data);
                                        return $data;
                                    })
                                    ->rules([
                                        function (Get $get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $salesOrderIds = $get('salesOrders') ?? [];
                                                if (empty($salesOrderIds) || empty($value)) {
                                                    return;
                                                }

                                                // Filter hanya item yang dipilih
                                                $selectedItems = collect($value)->filter(function ($item) {
                                                    return !empty($item['selected']) && !empty($item['quantity']) && $item['quantity'] > 0;
                                                });

                                                if ($selectedItems->isEmpty()) {
                                                    $fail("Minimal satu barang harus dipilih untuk dikirim.");
                                                    return;
                                                }

                                                // Validasi setiap selected item
                                                foreach ($selectedItems as $item) {
                                                    if (!empty($item['sale_order_item_id']) && !empty($item['quantity'])) {
                                                        $saleOrderItem = SaleOrderItem::find($item['sale_order_item_id']);
                                                        
                                                        if ($saleOrderItem) {
                                                            // Validasi 1: Quantity tidak boleh lebih besar dari quantity sale order item asli
                                                            if ($item['quantity'] > $saleOrderItem->quantity) {
                                                                $productName = $saleOrderItem->product->name ?? "Unknown Product";
                                                                $fail("Quantity untuk item '$productName' ({$item['quantity']}) tidak boleh lebih besar dari quantity sale order item ({$saleOrderItem->quantity}).");
                                                                return;
                                                            }

                                                            // Validasi 2: Quantity tidak boleh lebih besar dari remaining quantity
                                                            if ($item['quantity'] > $saleOrderItem->remaining_quantity) {
                                                                $productName = $saleOrderItem->product->name ?? "Unknown Product";
                                                                $fail("Quantity untuk item '$productName' ({$item['quantity']}) tidak boleh lebih besar dari sisa quantity yang tersedia ({$saleOrderItem->remaining_quantity}).");
                                                                return;
                                                            }
                                                        }
                                                    }
                                                }
                                                
                                                // Validasi 2: Pastikan tidak ada duplicate sale order item
                                                $saleOrderItemIds = $selectedItems->pluck('sale_order_item_id')->filter();
                                                $duplicates = $saleOrderItemIds->duplicates();
                                                
                                                if ($duplicates->isNotEmpty()) {
                                                    $fail("Tidak boleh ada duplicate sale order item dalam satu delivery order.");
                                                    return;
                                                }
                                            };
                                        },
                                    ])
                                    ->schema([
                                        \Filament\Forms\Components\Checkbox::make('selected')
                                            ->label('Pilih')
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                if (!$state) {
                                                    $set('quantity', 0);
                                                } else {
                                                    // Set default quantity ke remaining quantity
                                                    $saleOrderItemId = $get('sale_order_item_id');
                                                    if ($saleOrderItemId) {
                                                        $saleOrderItem = SaleOrderItem::find($saleOrderItemId);
                                                        if ($saleOrderItem) {
                                                            $set('quantity', $saleOrderItem->remaining_quantity);
                                                        }
                                                    }
                                                }

                                                // Update deliveryOrderItem for redundancy
                                                $selectedItems = $get('../../selected_items');
                                                $deliveryItems = [];
                                                foreach ($selectedItems as $item) {
                                                    if (!empty($item['selected']) && !empty($item['quantity']) && $item['quantity'] > 0) {
                                                        $deliveryItems[] = [
                                                            'options_from' => 2,
                                                            'sale_order_item_id' => $item['sale_order_item_id'],
                                                            'product_id' => $item['product_id'],
                                                            'quantity' => $item['quantity'],
                                                            'reason' => '',
                                                        ];
                                                    }
                                                }
                                                $set('../../deliveryOrderItem', $deliveryItems);
                                            }),
                                        \Filament\Forms\Components\Grid::make(4)
                                            ->schema([
                                                TextInput::make('product_name')
                                                    ->label('Product')
                                                    ->disabled()
                                                    ->columnSpan(2),
                                                TextInput::make('remaining_qty')
                                                    ->label('Sisa Qty')
                                                    ->disabled()
                                                    ->numeric(),
                                                TextInput::make('quantity')
                                                    ->label('Qty Kirim')
                                                    ->numeric()
                                                    ->reactive()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->rules(['required', 'numeric', 'min:0'])
                                                    ->disabled(function ($get) {
                                                        return !$get('selected');
                                                    })
                                                    ->afterStateUpdated(function ($state, $set, $get) {
                                                        $saleOrderItemId = $get('sale_order_item_id');
                                                        $remainingQty = $get('remaining_qty');

                                                        if ($state > $remainingQty) {
                                                            $set('quantity', $remainingQty);
                                                            \Filament\Notifications\Notification::make()
                                                                ->title('Quantity Validation')
                                                                ->body("Quantity tidak boleh melebihi sisa yang tersedia: {$remainingQty}")
                                                                ->warning()
                                                                ->send();
                                                        }
                                                    }),
                                            ])
                                            ->visible(function ($get) {
                                                return $get('selected');
                                            }),
                                        \Filament\Forms\Components\Hidden::make('sale_order_item_id'),
                                        \Filament\Forms\Components\Hidden::make('product_id'),
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        // Update deliveryOrderItem berdasarkan selected_items
                                        $deliveryItems = [];
                                        foreach ($state as $item) {
                                            if (!empty($item['selected']) && !empty($item['quantity']) && $item['quantity'] > 0) {
                                                $deliveryItems[] = [
                                                    'options_from' => 2,
                                                    'sale_order_item_id' => $item['sale_order_item_id'],
                                                    'product_id' => $item['product_id'],
                                                    'quantity' => $item['quantity'],
                                                ];
                                            }
                                        }
                                        $set('deliveryOrderItem', $deliveryItems);
                                    })
                                    ->itemLabel(function ($state) {
                                        if (!empty($state['product_name'])) {
                                            return $state['product_name'] . ' (Sisa: ' . ($state['remaining_qty'] ?? 0) . ')';
                                        }
                                        return 'Item';
                                    })
                            ])
                            ->visible(function ($get, $context) {
                                // Only show selected_items when creating new delivery order
                                // When editing, hide this section and use deliveryOrderItem repeater instead
                                return $context !== 'edit' && !empty($get('salesOrders'));
                            }),
                        Repeater::make('deliveryOrderItem')
                            ->relationship()
                            ->reactive()
                            ->columns(2)
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->mutateRelationshipDataBeforeFillUsing(function ($data) {
                                if ($data['sale_order_item_id']) {
                                    $data['options_from'] = 2;
                                } elseif ($data['purchase_receipt_item_id']) {
                                    $data['options_from'] = 1;
                                }
                                return $data;
                            })
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $salesOrderIds = $get('salesOrders') ?? [];
                                        if (empty($salesOrderIds) || empty($value)) {
                                            return;
                                        }

                                        // Validasi setiap delivery item
                                        foreach ($value as $deliveryItem) {
                                            if (!empty($deliveryItem['sale_order_item_id']) && !empty($deliveryItem['quantity'])) {
                                                $saleOrderItem = SaleOrderItem::find($deliveryItem['sale_order_item_id']);
                                                
                                                if ($saleOrderItem) {
                                                    // Validasi 1: Quantity delivery item tidak boleh lebih besar dari quantity sale order item asli
                                                    if ($deliveryItem['quantity'] > $saleOrderItem->quantity) {
                                                        $productName = $saleOrderItem->product->name ?? "Unknown Product";
                                                        $fail("Quantity untuk item '$productName' ({$deliveryItem['quantity']}) tidak boleh lebih besar dari quantity sale order item ({$saleOrderItem->quantity}).");
                                                        return;
                                                    }

                                                    // Validasi 2: Quantity delivery item tidak boleh lebih besar dari remaining quantity
                                                    if ($deliveryItem['quantity'] > $saleOrderItem->remaining_quantity) {
                                                        $productName = $saleOrderItem->product->name ?? "Unknown Product";
                                                        $fail("Quantity untuk item '$productName' ({$deliveryItem['quantity']}) tidak boleh lebih besar dari sisa quantity yang tersedia ({$saleOrderItem->remaining_quantity}).");
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
                                    ->options([
                                        '0' => 'None',
                                        '1' => 'From Receipt Item',
                                        '2' => 'From Sales Order Item'
                                    ])->default(function ($get, $set) {
                                        $listSalesOrderId = $get('../../salesOrders');
                                        if (count($listSalesOrderId) > 0) {
                                            $set('options_from', 2);
                                            return 2;
                                        }
                                        return 0;
                                    }),
                                Select::make('purchase_receipt_item_id')
                                    ->label('Purchase Receipt Item')
                                    ->preload()
                                    ->reactive()
                                    ->visible(function ($set, $get) {
                                        return $get('options_from') == 1;
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $purchaseReceiptItem = PurchaseReceiptItem::find($state);
                                        $set('product_id', $purchaseReceiptItem->product_id);
                                        $set('quantity', $purchaseReceiptItem->quantity);
                                    })
                                    ->searchable()
                                    ->relationship('purchaseReceiptItem', 'id')
                                    ->getOptionLabelFromRecordUsing(function (PurchaseReceiptItem $purchaseReceiptItem) {
                                        return "({$purchaseReceiptItem->product->sku}) {$purchaseReceiptItem->product->name}";
                                    })
                                    ->nullable(),
                                Select::make('sale_order_item_id')
                                    ->label('Sales Order Item')
                                    ->preload()
                                    ->reactive()
                                    ->visible(function ($set, $get) {
                                        return $get('options_from') == 2;
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $saleOrderItem = SaleOrderItem::find($state);
                                        if ($saleOrderItem) {
                                            $set('product_id', $saleOrderItem->product_id);
                                            $set('quantity', $saleOrderItem->remaining_quantity);
                                        }
                                    })
                                    ->searchable()
                                    ->relationship('saleOrderItem', 'id', function (Builder $query, $get) {
                                        $listSalesOrderId = $get('../../salesOrders');
                                        $query->when(count($listSalesOrderId) > 0, function (Builder $query) use ($listSalesOrderId) {
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
                                    ->relationship('product', 'id')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    })
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->reactive()
                                    ->default(0)
                                    ->rules(['required', 'numeric', 'min:1'])
                                    ->validationAttribute('quantity')
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get, $component) {
                                        $saleOrderItemId = $get('sale_order_item_id');
                                        $optionsFrom = $get('options_from');
                                        
                                        if ($optionsFrom == 2 && $saleOrderItemId) {
                                            $saleOrderItem = SaleOrderItem::find($saleOrderItemId);
                                            if ($saleOrderItem) {
                                                $originalQuantity = $saleOrderItem->quantity;
                                                $remainingQuantity = $saleOrderItem->remaining_quantity;
                                                
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
                                    ->helperText(function ($get) {
                                        $saleOrderItemId = $get('sale_order_item_id');
                                        $optionsFrom = $get('options_from');
                                        
                                        if ($optionsFrom == 2 && $saleOrderItemId) {
                                            $saleOrderItem = SaleOrderItem::find($saleOrderItemId);
                                            if ($saleOrderItem) {
                                                $remaining = $saleOrderItem->remaining_quantity;
                                                $delivered = $saleOrderItem->delivered_quantity;
                                                $total = $saleOrderItem->quantity;
                                                
                                                return "Total SO: {$total} | Sudah dikirim: {$delivered} | Sisa: {$remaining} | Max yang bisa dikirim: {$remaining}";
                                            }
                                        }
                                        
                                        return null;
                                    }),
                                Textarea::make('reason')
                                    ->label('Reason')
                                    ->nullable()
                            ])
                            ->visible(function ($get, $context) {
                                // Show deliveryOrderItem repeater when editing OR when creating (to enable relationship saving)
                                return $context === 'edit' || !empty($get('salesOrders'));
                            })
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
                        TextEntry::make('driver.name')->label('Driver'),
                        TextEntry::make('vehicle.plate')->label('Vehicle'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('notes'),
                        TextEntry::make('additional_cost')->money('IDR'),
                        TextEntry::make('additional_cost_description'),
                    ])->columns(2),
                Section::make('Sales Orders')
                    ->schema([
                        RepeatableEntry::make('salesOrders')
                            ->label('')
                            ->schema([
                                TextEntry::make('so_number')->label('SO Number'),
                                TextEntry::make('createdBy.name')->label('Sales'),
                            ]),
                    ]),
                Section::make('Delivery Order Items')
                    ->schema([
                        RepeatableEntry::make('deliveryOrderItem')
                            ->label('')
                            ->schema([
                                TextEntry::make('product.name')->label('Product'),
                                TextEntry::make('quantity'),
                                TextEntry::make('reason'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('do_number')
                    ->label('Delivery Order Number')
                    ->searchable(),
                TextColumn::make('delivery_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('driver.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('vehicle')
                    ->label('Vehicle')
                    ->formatStateUsing(function ($state) {
                        return $state->plate . ' - ' . $state->type;
                    }),
                TextColumn::make('status')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'sent' => 'primary',
                            'received' => 'info',
                            'supplier' => 'warning',
                            'completed' => 'success',
                            'request_approve' => 'primary',
                            'approved' => 'primary',
                            'request_close' => 'warning',
                            'closed' => 'danger',
                            'reject' => 'danger',
                        };
                    })
                    ->badge(),
                TextColumn::make('surat_jalan_status')
                    ->label('Surat Jalan')
                    ->formatStateUsing(function ($record) {
                        return $record->suratJalan()->exists() ? 'Ada' : 'Belum Ada';
                    })
                    ->color(function ($record) {
                        return $record->suratJalan()->exists() ? 'success' : 'warning';
                    })
                    ->badge()
                    ->tooltip(function ($record) {
                        if ($record->suratJalan()->exists()) {
                            $suratJalan = $record->suratJalan()->first();
                            if ($suratJalan) {
                                return "Surat Jalan: {$suratJalan->sj_number}\nStatus: {$suratJalan->status}";
                            }
                        }
                        return 'Delivery Order belum memiliki Surat Jalan. Surat Jalan diperlukan sebelum approval.';
                    }),
                TextColumn::make('salesOrders.so_number')
                    ->label('Sales Orders')
                    ->badge()
                    ->searchable(),
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
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'received' => 'Received',
                        'supplier' => 'Supplier',
                        'completed' => 'Completed',
                        'request_approve' => 'Request Approve',
                        'approved' => 'Approved',
                        'request_close' => 'Request Close',
                        'closed' => 'Closed',
                        'reject' => 'Reject',
                    ]),
                SelectFilter::make('driver_id')
                    ->relationship('driver', 'name')
                    ->label('Driver')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('vehicle_id')
                    ->relationship('vehicle', 'plate')
                    ->label('Vehicle')
                    ->preload()
                    ->searchable(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
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
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary'),
                    EditAction::make()
                        ->color('success'),
                    DeleteAction::make(),
                    Action::make('request_approve')
                        ->label('Request Approve')
                        ->requiresConfirmation()
                        ->color('success')
                        ->icon('heroicon-o-arrow-uturn-up')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request delivery order') && $record->status == 'draft';
                        })
                        ->action(function ($record) {
                            $deliveryOrderService = app(DeliveryOrderService::class);
                            $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'request_approve');
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan request approve");
                        }),
                    Action::make('request_close')
                        ->label('Request Close')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request delivery order') && ($record->status != 'approved' || $record->status != 'confirmed' || $record->status != 'close' || $record->status != 'canceled' || $record->status == 'draft');
                        })
                        ->action(function ($record) {
                            $deliveryOrderService = app(DeliveryOrderService::class);
                            $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'request_close');
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan request close");
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->requiresConfirmation()
                        ->color('success')
                        ->icon('heroicon-o-check-badge')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response delivery order') &&
                                   $record->status == 'request_approve' &&
                                   $record->suratJalan()->exists();
                        })
                        ->form([
                            Textarea::make('comments')
                                ->label('Comments')
                                ->placeholder('Optional approval comments...')
                                ->nullable()
                        ])
                        ->action(function ($record, array $data) {
                            try {
                                $deliveryOrderService = app(DeliveryOrderService::class);
                                $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'approved', comments: $data['comments'] ?? null, action: 'approved');

                                HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan approve Delivery Order");
                            } catch (\Exception $e) {
                                HelperController::sendNotification(isSuccess: false, title: "Error", message: $e->getMessage());
                                throw $e;
                            }
                        }),
                    Action::make('closed')
                        ->label('Close')
                        ->requiresConfirmation()
                        ->color('warning')
                        ->icon('heroicon-o-x-circle')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response delivery order') && ($record->status == 'request_close');
                        })
                        ->action(function ($record) {
                            $deliveryOrderService = app(DeliveryOrderService::class);
                            $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'closed');
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Delivery Order Closed");
                        }),
                    Action::make('reject')
                        ->label('Reject')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response delivery order') && ($record->status == 'request_approve');
                        })
                        ->form([
                            Textarea::make('comments')
                                ->label('Rejection Reason')
                                ->placeholder('Please provide reason for rejection...')
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
                            
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan Reject Delivery Order");
                        }),
                    Action::make('sent')
                        ->label('Mark as Sent')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Delivery Order as Sent')
                        ->modalDescription('Are you sure you want to mark this delivery order as sent? This will create journal entries for goods delivery.')
                        ->modalSubmitActionLabel('Yes, Mark as Sent')
                        ->color('info')
                        ->icon('heroicon-o-paper-airplane')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response delivery order') &&
                                   $record->status == 'approved';
                        })
                        ->action(function ($record) {
                            try {
                                $deliveryOrderService = app(DeliveryOrderService::class);
                                $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'sent');
                                HelperController::sendNotification(isSuccess: true, title: "Success", message: "Delivery Order marked as sent successfully");
                            } catch (\Exception $e) {
                                HelperController::sendNotification(isSuccess: false, title: "Error", message: $e->getMessage());
                                throw $e;
                            }
                        }),
                    Action::make('pdf_delivery_order')
                        ->label('Download PDF')
                        ->color('danger')
                        ->visible(function ($record) {
                            return $record->status == 'approved' || $record->status == 'completed' || $record->status == 'confirmed' || $record->status == 'received';
                        })
                        ->icon('heroicon-o-document')
                        ->action(function ($record) {
                            $pdf = Pdf::loadView('pdf.delivery-order', [
                                'deliveryOrder' => $record
                            ])->setPaper('A4', 'potrait');

                        }),
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

                                        // Update delivered_quantity di sale order item
                                        if ($deliveryItem->sale_order_item_id) {
                                            $saleOrderItem = $deliveryItem->saleOrderItem;
                                            if ($saleOrderItem) {
                                                // Hitung total delivered quantity dari semua delivery orders yang sudah sent/completed
                                                $totalDelivered = $saleOrderItem->deliveryOrderItems()
                                                    ->whereHas('deliveryOrder', function ($query) {
                                                        $query->whereIn('status', ['sent', 'received', 'completed']);
                                                    })
                                                    ->sum('quantity');

                                                $saleOrderItem->update([
                                                    'delivered_quantity' => $totalDelivered
                                                ]);
                                            }
                                        }

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
                    Action::make('completed')
                        ->label('Complete')
                        ->icon('heroicon-o-check-badge')
                        ->requiresConfirmation()
                        ->visible(function ($record) {
                            return Auth::user()->hasRole(['Super Admin', 'Owner']) && $record->status == 'sent';
                        })
                        ->color('success')
                        ->action(function ($record) {
                            $deliveryOrderService = app(DeliveryOrderService::class);
                            $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'completed');
                            // Post delivery order to general ledger for HPP recognition
                            $postResult = $deliveryOrderService->postDeliveryOrder($record);
                            if ($postResult['status'] === 'posted') {
                                HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order Completed and posted to ledger");
                            } elseif ($postResult['status'] === 'error') {
                                HelperController::sendNotification(isSuccess: false, title: "Error", message: "Sales Order Completed but posting failed: " . $postResult['message']);
                            } else {
                                HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order Completed");
                            }
                        }),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Delivery Order</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Delivery Order adalah dokumen pengiriman barang dari penjualan yang perlu disetujui sebelum dikirim.</li>' .
                            '<li><strong>Flow Approval:</strong> Draft → Request Approve → Approved → Sent → Received → Completed. Gunakan tombol <em>Request Approve</em> untuk memulai proses approval.</li>' .
                            '<li><strong>Surat Jalan:</strong> Wajib memiliki Surat Jalan sebelum approval. Status Surat Jalan ditampilkan di kolom khusus.</li>' .
                            '<li><strong>Actions:</strong> <em>Request Approve</em> (draft), <em>Approve/Reject</em> (request_approve), <em>Mark as Sent</em> (approved), <em>Complete</em> (received).</li>' .
                            '<li><strong>Checker Edit:</strong> User dengan role Checker dapat mengedit quantity setelah approved untuk penyesuaian aktual.</li>' .
                            '<li><strong>PDF:</strong> Download PDF tersedia setelah status approved atau completed.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
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
        return parent::getEloquentQuery()->orderBy('delivery_date', 'DESC');
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
