<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesInvoiceResource\Pages;
use App\Http\Controllers\HelperController;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\DeliveryOrder;
use App\Models\Customer;
use App\Models\Cabang;
use App\Services\InvoiceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Invoice Penjualan';
    protected static ?string $modelLabel = 'Invoice Penjualan';
    protected static ?string $pluralModelLabel = 'Invoice Penjualan';
    protected static ?string $navigationGroup = 'Finance - Penjualan';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Form Invoice')
                    ->schema([
                        // Header Section - Sumber Invoice
                        Section::make('Sumber Invoice')
                            ->description('Silahkan Pilih Customer')
                            ->columns(2)
                            ->schema([
                                Select::make('selected_customer')
                                    ->label('Customer')
                                    ->options(Customer::all()->mapWithKeys(function ($customer) {
                                        return [$customer->id => "({$customer->code}) {$customer->name}"];
                                    }))
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Customer harus dipilih'
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_sale_order', null);
                                        $set('selected_delivery_orders', []);
                                        $set('invoiceItem', []);
                                        $set('subtotal', 0);
                                        $set('total', 0);
                                        $set('other_fees', []);
                                        $set('dpp', 0);
                                    }),
                                    
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
                                    ->validationMessages([
                                        'required' => 'Cabang harus dipilih'
                                    ]),
                                    
                                Select::make('selected_sale_order')
                                    ->label('SO (Sales Order)')
                                    ->options(function ($get) {
                                        $customerId = $get('selected_customer');
                                        if (!$customerId) return [];
                                        
                                        return SaleOrder::where('customer_id', $customerId)
                                            ->where('status', 'completed')
                                            ->get()
                                            ->mapWithKeys(function ($so) {
                                                return [$so->id => $so->so_number];
                                            });
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_delivery_orders', []);
                                        $set('invoiceItem', []);
                                        $set('subtotal', 0);
                                        $set('total', 0);
                                        $set('other_fees', []);
                                        $set('dpp', 0);
                                    }),
                            ]),

                        // Invoice Info Section
                        Section::make()
                            ->columns(2)
                            ->schema([
                                TextInput::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Nomor invoice tidak boleh kosong',
                                        'max' => 'Nomor invoice terlalu panjang'
                                    ])
                                    ->suffixAction(
                                        Action::make('generate')
                                            ->icon('heroicon-m-arrow-path')
                                            ->tooltip('Generate Invoice Number')
                                            ->action(function ($set, $get) {
                                                $invoiceService = app(InvoiceService::class);
                                                $set('invoice_number', $invoiceService->generateInvoiceNumber());
                                            })
                                    )
                                    ->maxLength(255),
                                    
                                TextInput::make('due_date_display')
                                    ->label('Due Date')
                                    ->disabled()
                                    ->placeholder('Auto calculated'),
                                    
                                DatePicker::make('invoice_date')
                                    ->label('Invoice Date')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Tanggal invoice harus diisi'
                                    ])
                                    ->default(now()),
                                    
                                DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Tanggal jatuh tempo harus diisi'
                                    ])
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if ($state) {
                                            $set('due_date_display', $state);
                                        }
                                    }),
                            ]),

                        // Delivery Orders Selection / Direct SO Items
                        Section::make('Silahkan Pilih DO atau Items')
                            ->schema([
                                Forms\Components\CheckboxList::make('selected_delivery_orders')
                                    ->label('')
                                    ->options(function ($get) {
                                        $saleOrderId = $get('selected_sale_order');
                                        if (!$saleOrderId) return [];

                                        $saleOrder = SaleOrder::find($saleOrderId);
                                        if (!$saleOrder) return [];

                                        // Jika SO tipe "Ambil Sendiri", return empty array (akan handle di bawah)
                                        if ($saleOrder->tipe_pengiriman === 'Ambil Sendiri') {
                                            return [];
                                        }

                                        // Get all DOs from this SO
                                        $deliveryOrders = $saleOrder->deliverySalesOrder()
                                            ->with(['deliveryOrder' => function($query) {
                                                $query->with('deliveryOrderItem.saleOrderItem');
                                            }])
                                            ->get()
                                            ->pluck('deliveryOrder')
                                            ->filter(function ($do) {
                                                return $do && $do->status === 'completed';
                                            });

                                        // Get current invoice record if editing (for allowing already selected DOs)
                                        $currentInvoiceId = $get('id') ?? null;

                                        // Check which DOs are already invoiced (exclude current invoice if editing)
                                        $invoicedDOIds = Invoice::where('from_model_type', 'App\Models\SaleOrder')
                                            ->whereNotNull('delivery_orders')
                                            ->when($currentInvoiceId, function ($query) use ($currentInvoiceId) {
                                                return $query->where('id', '!=', $currentInvoiceId);
                                            })
                                            ->get()
                                            ->pluck('delivery_orders')
                                            ->flatten()
                                            ->unique()
                                            ->toArray();

                                        $options = [];
                                        foreach ($deliveryOrders as $do) {
                                            $isInvoiced = in_array($do->id, $invoicedDOIds);
                                            $total = $do->total ?? 0;
                                            $label = "{$do->do_number} - Rp. " . number_format($total, 0, ',', '.');

                                            if ($isInvoiced) {
                                                $label .= " (Sudah di-invoice)";
                                                // Don't add to options if already invoiced
                                                continue;
                                            }

                                            $options[$do->id] = $label;
                                        }

                                        return $options;
                                    })
                                    ->columns(1)
                                    ->reactive()
                                    ->visible(function ($get) {
                                        $saleOrderId = $get('selected_sale_order');
                                        if (!$saleOrderId) return false;

                                        $saleOrder = SaleOrder::find($saleOrderId);
                                        return $saleOrder && $saleOrder->tipe_pengiriman !== 'Ambil Sendiri';
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if (!$state || empty($state)) {
                                            $set('invoiceItem', []);
                                            $set('subtotal', 0);
                                            $set('total', 0);
                                            $set('other_fees', []);
                                            $set('dpp', 0);
                                            $set('delivery_order_items', []);
                                            return;
                                        }

                                        $saleOrderId = $get('selected_sale_order');
                                        $customerId = $get('selected_customer');

                                        if (!$saleOrderId || !$customerId) return;

                                        $saleOrder = SaleOrder::with('customer')->find($saleOrderId);
                                        $deliveryOrders = DeliveryOrder::with('deliveryOrderItem.saleOrderItem', 'deliveryOrderItem.product')
                                            ->whereIn('id', $state)
                                            ->get();

                                        // Set customer info
                                        $set('customer_name', $saleOrder->customer->name);
                                        $set('customer_phone', $saleOrder->customer->phone ?? '');
                                        $set('from_model_type', 'App\Models\SaleOrder');
                                        $set('from_model_id', $saleOrderId);

                                        // Calculate items from delivery orders
                                        $items = [];
                                        $subtotal = 0;

                                        foreach ($deliveryOrders as $do) {
                                            foreach ($do->deliveryOrderItem as $item) {
                                                if ($item->saleOrderItem) {
                                                    $price = (float) $item->saleOrderItem->unit_price - (float) $item->saleOrderItem->discount + (float) $item->saleOrderItem->tax;
                                                    $total = (float) $price * (float) $item->quantity;

                                                    $items[] = [
                                                        'product_id' => $item->product_id,
                                                        'quantity' => $item->quantity,
                                                        'price' => $price,
                                                        'total' => $total
                                                    ];

                                                    $subtotal += $total;
                                                }
                                            }
                                        }

                                        $set('invoiceItem', $items);
                                        $set('subtotal', $subtotal);
                                        $set('dpp', $subtotal);
                                        $set('delivery_orders', $state);
                                        $set('other_fees', []);

                                        // For create, set delivery_order_items
                                        if (!$get('id')) {
                                            $deliveryOrderItems = [];
                                            foreach ($deliveryOrders as $do) {
                                                foreach ($do->deliveryOrderItem as $item) {
                                                    if ($item->product && $item->saleOrderItem) {
                                                        $originalPrice = $item->saleOrderItem->unit_price - $item->saleOrderItem->discount + $item->saleOrderItem->tax;

                                                        $deliveryOrderItems[] = [
                                                            'do_number' => $do->do_number,
                                                            'product_id' => $item->product_id,
                                                            'product_name' => $item->product->name . ' (' . $item->product->sku . ')',
                                                            'original_quantity' => $item->quantity,
                                                            'invoice_quantity' => $item->quantity,
                                                            'original_price' => $originalPrice,
                                                            'unit_price' => $originalPrice,
                                                            'total_price' => (float) $originalPrice * (float) $item->quantity,
                                                            'coa_id' => $item->product->sales_coa_id,
                                                        ];
                                                    }
                                                }
                                            }
                                            $set('delivery_order_items', $deliveryOrderItems);
                                        }

                                        // Calculate tax and total
                                        $tax = $get('tax') ?? 0;
                                        $otherFee = 0; // Initialize as 0
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $otherFee + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                    }),

                                // Checkbox untuk konfirmasi pemilihan SO Ambil Sendiri
                                Forms\Components\Checkbox::make('confirm_self_pickup_invoice')
                                    ->label('Buat invoice dari Sales Order "Ambil Sendiri" ini')
                                    ->visible(function ($get) {
                                        $saleOrderId = $get('selected_sale_order');
                                        if (!$saleOrderId) return false;

                                        $saleOrder = SaleOrder::find($saleOrderId);
                                        return $saleOrder && $saleOrder->tipe_pengiriman === 'Ambil Sendiri';
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if (!$state) {
                                            $set('invoiceItem', []);
                                            $set('subtotal', 0);
                                            $set('total', 0);
                                            $set('other_fees', []);
                                            $set('dpp', 0);
                                            $set('delivery_order_items', []);
                                            return;
                                        }

                                        $saleOrderId = $get('selected_sale_order');
                                        $customerId = $get('selected_customer');

                                        if (!$saleOrderId || !$customerId) return;

                                        $saleOrder = SaleOrder::with('customer', 'saleOrderItem.product')->find($saleOrderId);

                                        // Set customer info
                                        $set('customer_name', $saleOrder->customer->name);
                                        $set('customer_phone', $saleOrder->customer->phone ?? '');
                                        $set('from_model_type', 'App\Models\SaleOrder');
                                        $set('from_model_id', $saleOrderId);

                                        // Calculate items directly from SO items
                                        $items = [];
                                        $subtotal = 0;

                                        foreach ($saleOrder->saleOrderItem as $item) {
                                            $price = (float) $item->unit_price - (float) $item->discount + (float) $item->tax;
                                            $total = (float) $price * (float) $item->quantity;

                                            $items[] = [
                                                'product_id' => $item->product_id,
                                                'quantity' => $item->quantity,
                                                'price' => $price,
                                                'total' => $total
                                            ];

                                            $subtotal += $total;
                                        }

                                        $set('invoiceItem', $items);
                                        $set('subtotal', $subtotal);
                                        $set('dpp', $subtotal);
                                        $set('delivery_orders', []); // Empty for self-pickup
                                        $set('other_fees', []);

                                        // For create, set delivery_order_items from SO items
                                        if (!$get('id')) {
                                            $deliveryOrderItems = [];
                                            foreach ($saleOrder->saleOrderItem as $item) {
                                                if ($item->product) {
                                                    $originalPrice = $item->unit_price - $item->discount + $item->tax;

                                                    $deliveryOrderItems[] = [
                                                        'do_number' => 'SO-' . $saleOrder->so_number, // Use SO number as reference
                                                        'product_id' => $item->product_id,
                                                        'product_name' => $item->product->name . ' (' . $item->product->sku . ')',
                                                        'original_quantity' => $item->quantity,
                                                        'invoice_quantity' => $item->quantity,
                                                        'original_price' => $originalPrice,
                                                        'unit_price' => $originalPrice,
                                                        'total_price' => (float) $originalPrice * (float) $item->quantity,
                                                        'coa_id' => $item->product->sales_coa_id,
                                                    ];
                                                }
                                            }
                                            $set('delivery_order_items', $deliveryOrderItems);
                                        }

                                        // Calculate tax and total
                                        $tax = $get('tax') ?? 0;
                                        $otherFee = 0;
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $otherFee + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                    }),
                            ]),

                        // Edit Delivery Order Items Section
                        Section::make('Edit Item Delivery Order')
                            ->description('Edit quantity dan harga dari delivery order yang dipilih')
                            ->schema([
                                Repeater::make('delivery_order_items')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('do_number')
                                            ->label('No. DO')
                                            ->disabled()
                                            ->columnSpan(1),
                                        TextInput::make('product_name')
                                            ->label('Product')
                                            ->disabled()
                                            ->columnSpan(2),
                                        TextInput::make('original_quantity')
                                            ->label('Qty DO Asli')
                                            ->disabled()
                                            ->numeric()
                                            ->columnSpan(1),
                                        TextInput::make('invoice_quantity')
                                            ->label('Qty untuk Invoice')
                                            ->numeric()
                                            ->required()
                                            ->default(function ($get) {
                                                return $get('original_quantity') ?? 0;
                                            })
                                            ->minValue(0)
                                            ->maxValue(function ($get) {
                                                return $get('original_quantity') ?? 0;
                                            })
                                            ->validationMessages([
                                                'required' => 'Qty invoice tidak boleh kosong',
                                                'numeric' => 'Qty invoice harus berupa angka',
                                                'min' => 'Qty invoice tidak boleh negatif',
                                                'max' => 'Qty invoice tidak boleh lebih dari qty DO asli'
                                            ])
                                            ->reactive()
                                            ->afterStateUpdated(function ($set, $get) {
                                                $quantity = (float) ($get('invoice_quantity') ?? 0);
                                                $price = (float) str_replace(['.', ','], ['', '.'], $get('unit_price') ?? '0');
                                                $set('total_price', $quantity * $price);
                                            })
                                            ->columnSpan(1),
                                        TextInput::make('unit_price')
                                            ->label('Harga Satuan')
                                            ->indonesianMoney()
                                            ->required()
                                            ->default(function ($get) {
                                                return $get('original_price') ?? 0;
                                            })
                                            ->minValue(0)
                                            ->validationMessages([
                                                'required' => 'Harga satuan tidak boleh kosong',
                                                'numeric' => 'Harga satuan harus berupa angka',
                                                'min' => 'Harga satuan tidak boleh negatif'
                                            ])
                                            ->reactive()
                                            ->afterStateUpdated(function ($set, $get) {
                                                $quantity = (float) ($get('invoice_quantity') ?? 0);
                                                $price = (float) str_replace(['.', ','], ['', '.'], $get('unit_price') ?? '0');
                                                $set('total_price', $quantity * $price);
                                            })
                                            ->columnSpan(1),
                                        TextInput::make('total_price')
                                            ->label('Total')
                                            ->indonesianMoney()
                                            ->disabled()
                                            ->columnSpan(1),
                                        Select::make('coa_id')
                                            ->label('COA Revenue')
                                            ->options(\App\Models\ChartOfAccount::all()->mapWithKeys(function ($coa) {
                                                return [$coa->id => "({$coa->code}) {$coa->name}"];
                                            }))
                                            ->searchable()
                                            ->preload()
                                            ->default(function ($get) {
                                                $productId = $get('product_id');
                                                if ($productId) {
                                                    $product = \App\Models\Product::find($productId);
                                                    return $product?->sales_coa_id;
                                                }
                                                return null;
                                            })
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'COA revenue harus dipilih'
                                            ])
                                            ->columnSpan(1),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull()
                                    ->defaultItems(0)
                                    ->itemLabel(function ($state) {
                                        return $state['product_name'] ?? 'Item';
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        // Recalculate invoice items when delivery order items change
                                        $deliveryOrderItems = $state ?? [];
                                        
                                        $invoiceItems = [];
                                        $subtotal = 0;
                                        
                                        foreach ($deliveryOrderItems as $item) {
                                            $quantity = (float) ($item['invoice_quantity'] ?? 0);
                                            $price = (float) ($item['unit_price'] ?? 0);
                                            $total = $quantity * $price;
                                            
                                            $invoiceItems[] = [
                                                'product_id' => $item['product_id'] ?? null,
                                                'quantity' => $quantity,
                                                'price' => $price,
                                                'total' => $total,
                                                'coa_id' => $item['coa_id'] ?? null,
                                            ];
                                            
                                            $subtotal += $total;
                                        }
                                        
                                        $set('invoiceItem', $invoiceItems);
                                        $set('subtotal', $subtotal);
                                        $set('dpp', $subtotal);
                                        
                                        // Recalculate total
                                        $tax = $get('tax') ?? 0;
                                        $otherFees = $get('other_fees') ?? [];
                                        $otherFeeTotal = collect($otherFees)->sum('amount');
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $otherFeeTotal + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                        $set('other_fee', $otherFeeTotal);
                                    }),
                            ]),

                        // Biaya Lain Section
                        Section::make('Biaya Lain - lain')
                            ->schema([
                                Repeater::make('other_fees')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nama Biaya')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Nama biaya tidak boleh kosong'
                                            ])
                                            ->default('Biaya Lain'),
                                        TextInput::make('amount')
                                            ->label('Jumlah')
                                            ->indonesianMoney()
                                            ->numeric()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Jumlah tidak boleh kosong',
                                                'numeric' => 'Jumlah harus berupa angka'
                                            ])
                                            ->default(0)
                                            ->reactive(),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $totalOtherFee = collect($state ?? [])->sum('amount');
                                        $set('other_fee', $totalOtherFee);
                                        
                                        $subtotal = $get('subtotal') ?? 0;
                                        $tax = $get('tax') ?? 0;
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $totalOtherFee + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                    })
                                    ->collapsible(),
                            ]),

                        // Tax and Total Section
                        Section::make()
                            ->columns(3)
                            ->schema([
                                TextInput::make('dpp')
                                    ->label('DPP (Dasar Pengenaan Pajak)')
                                    ->indonesianMoney()
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => 'DPP harus berupa angka'
                                    ])
                                    ->default(0)
                                    ->readonly(),
                                    
                                TextInput::make('tax')
                                    ->label('Tax (%)')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => 'Tax harus berupa angka'
                                    ])
                                    ->suffix('%')
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $state = $state ?? 0; // Ensure it's not null
                                        $subtotal = $get('subtotal') ?? 0;
                                        $otherFees = $get('other_fees') ?? [];
                                        $otherFeeTotal = collect($otherFees)->sum('amount');
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $otherFeeTotal + ($subtotal * $state / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                        $set('other_fee', $otherFeeTotal);
                                    }),
                                    
                                TextInput::make('ppn_rate')
                                    ->label('PPN Rate (%)')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => 'PPN rate harus berupa angka'
                                    ])
                                    ->suffix('%')
                                    ->default(function () {
                                        $taxSetting = \App\Models\TaxSetting::where('status', true)
                                            ->where('effective_date', '<=', now())
                                            ->where('type', 'PPN')
                                            ->orderByDesc('effective_date')
                                            ->first();
                                        return $taxSetting?->rate ?? 11;
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $state = $state ?? 11; // Ensure it's not null, default to 11
                                        $subtotal = $get('subtotal') ?? 0;
                                        $otherFees = $get('other_fees') ?? [];
                                        $otherFeeTotal = collect($otherFees)->sum('amount');
                                        $tax = $get('tax') ?? 0;
                                        $finalTotal = $subtotal + $otherFeeTotal + ($subtotal * $tax / 100) + ($subtotal * $state / 100);
                                        $set('total', $finalTotal);
                                        $set('other_fee', $otherFeeTotal);
                                    }),
                            ]),

                        // Grand Total
                        Section::make('Grand Total Invoice')
                            ->schema([
                                TextInput::make('total')
                                    ->label('')
                                    ->indonesianMoney()
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => 'Total harus berupa angka'
                                    ])
                                    ->readonly()
                                    ->extraAttributes(['class' => 'text-lg font-bold']),
                            ]),

                        // COA Selection
                        Section::make('Pilih COA untuk Journal Entries')
                            ->description('Pilih COA yang akan digunakan untuk journal entries invoice penjualan')
                            ->columns(2)
                            ->schema([
                                Select::make('ar_coa_id')
                                    ->label('COA Piutang Usaha (AR)')
                                    ->options(\App\Models\ChartOfAccount::all()->mapWithKeys(function ($coa) {
                                        return [$coa->id => "({$coa->code}) {$coa->name}"];
                                    }))
                                    ->searchable()
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '1120')->first()?->id;
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'COA piutang usaha harus dipilih'
                                    ]),
                                    
                                Select::make('revenue_coa_id')
                                    ->label('COA Penjualan (Revenue)')
                                    ->options(\App\Models\ChartOfAccount::all()->mapWithKeys(function ($coa) {
                                        return [$coa->id => "({$coa->code}) {$coa->name}"];
                                    }))
                                    ->searchable()
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '4000')->first()?->id;
                                    })
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'COA penjualan harus dipilih'
                                    ]),
                                    
                                Select::make('ppn_keluaran_coa_id')
                                    ->label('COA PPn Keluaran')
                                    ->options(\App\Models\ChartOfAccount::all()->mapWithKeys(function ($coa) {
                                        return [$coa->id => "({$coa->code}) {$coa->name}"];
                                    }))
                                    ->searchable()
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '2120.06')->first()?->id;
                                    }),
                            ]),
                            
                        // Hidden fields
                        Hidden::make('id'),
                        Hidden::make('from_model_type')->default('App\Models\SaleOrder'),
                        Hidden::make('from_model_id'),
                        Hidden::make('customer_name'),
                        Hidden::make('customer_phone'),
                        Hidden::make('subtotal')->default(0),
                        Hidden::make('status')->default('draft'),
                        Hidden::make('delivery_orders'),
                        Hidden::make('dpp')->default(0),
                        Hidden::make('tax')->default(0),
                        Hidden::make('ppn_rate')->default(function () {
                            $taxSetting = \App\Models\TaxSetting::where('status', true)
                                ->where('effective_date', '<=', now())
                                ->where('type', 'PPN')
                                ->orderByDesc('effective_date')
                                ->first();
                            return $taxSetting?->rate ?? 11;
                        }),
                        Hidden::make('total')->default(0),
                        
                        Repeater::make('invoiceItem')
                            ->label('Item Invoice')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(function () {
                                        return \App\Models\Product::all()->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Produk harus dipilih'
                                    ]),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Qty tidak boleh kosong',
                                        'numeric' => 'Qty harus berupa angka'
                                    ]),
                                TextInput::make('price')
                                    ->label('Price')
                                    ->numeric()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Harga tidak boleh kosong',
                                        'numeric' => 'Harga harus berupa angka'
                                    ]),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Total tidak boleh kosong',
                                        'numeric' => 'Total harus berupa angka'
                                    ]),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->collapsed()
                            ->cloneable(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->where('from_model_type', 'App\Models\SaleOrder');
            })
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('customer_phone')
                    ->label('No. Telepon')
                    ->searchable(),
                    
                TextColumn::make('invoice_date')
                    ->label('Tanggal Invoice')
                    ->date()
                    ->sortable(),
                    
                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date()
                    ->sortable(),
                    
                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
                    
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'sent',
                        'success' => 'paid',
                        'primary' => 'partially_paid',
                        'danger' => 'overdue',
                    ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Terkirim',
                        'paid' => 'Lunas',
                        'partially_paid' => 'Dibayar Sebagian',
                        'overdue' => 'Terlambat',
                    ]),
                SelectFilter::make('customer_name')
                    ->label('Customer')
                    ->options(function () {
                        return Invoice::whereNotNull('customer_name')
                            ->distinct()
                            ->pluck('customer_name', 'customer_name')
                            ->toArray();
                    })
                    ->searchable(),
                Filter::make('invoice_date')
                    ->label('Tanggal Invoice')
                    ->form([
                        DatePicker::make('invoice_date_from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('invoice_date_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['invoice_date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('invoice_date', '>=', $date),
                            )
                            ->when(
                                $data['invoice_date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('invoice_date', '<=', $date),
                            );
                    }),
                Filter::make('due_date')
                    ->label('Jatuh Tempo')
                    ->form([
                        DatePicker::make('due_date_from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('due_date_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date),
                            )
                            ->when(
                                $data['due_date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Tables\Actions\Action::make('view_journal_entries')
                        ->label('Lihat Journal Entries')
                        ->icon('heroicon-o-book-open')
                        ->color('success')
                        ->action(function ($record) {
                            $journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\Invoice::class)
                                ->where('source_id', $record->id)
                                ->get();

                            if ($journalEntries->count() === 1) {
                                // Jika hanya 1 journal entry, langsung ke halaman detail
                                $entry = $journalEntries->first();
                                return redirect()->to("/admin/journal-entries/{$entry->id}");
                            } else {
                                // Jika multiple entries, gunakan filter
                                $sourceType = urlencode(\App\Models\Invoice::class);
                                $sourceId = $record->id;
                                return redirect()->to("/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][value]={$sourceId}");
                            }
                        }),
                    DeleteAction::make(),
                    ])
                    ],position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Sales Invoice</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Sales Invoice adalah faktur penjualan kepada customer berdasarkan Sale Order yang telah disetujui, digunakan untuk mencatat pendapatan dan memproses penerimaan pembayaran.</li>' .
                            '<li><strong>Status Flow:</strong> Dibuat dari Sale Order yang confirmed, dapat diedit sebelum dikirim.</li>' .
                            '<li><strong>Validasi:</strong> Subtotal, Tax, PPN dihitung otomatis berdasarkan item. Total invoice digunakan untuk Account Receivable.</li>' .
                            '<li><strong>Actions:</strong> <em>View</em> (lihat detail), <em>Edit</em> (ubah invoice), <em>Delete</em> (hapus).</li>' .
                            '<li><strong>Filters:</strong> Customer, Status, Date Range, Amount Range, Due Date Range, dll.</li>' .
                            '<li><strong>Permissions:</strong> Tergantung pada cabang user, hanya menampilkan invoice dari cabang tersebut jika tidak memiliki akses all.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan Sale Order dan menghasilkan Account Receivable.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('from_model_type', 'App\Models\SaleOrder')
            ->with([
                'invoiceItem.product',
                'fromModel',
                'accountReceivable',
                'accountPayable'
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
            'index' => Pages\ListSalesInvoices::route('/'),
            'create' => Pages\CreateSalesInvoice::route('/create'),
            'view' => Pages\ViewSalesInvoice::route('/{record}'),
            'edit' => Pages\EditSalesInvoice::route('/{record}/edit'),
        ];
    }
}
