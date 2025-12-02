<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesInvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\DeliveryOrder;
use App\Models\Customer;
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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
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
    protected static ?int $navigationSort = 10;

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

                        // Delivery Orders Selection
                        Section::make('Silahkan Pilih DO')
                            ->schema([
                                Forms\Components\CheckboxList::make('selected_delivery_orders')
                                    ->label('')
                                    ->options(function ($get) {
                                        $saleOrderId = $get('selected_sale_order');
                                        if (!$saleOrderId) return [];
                                        
                                        $saleOrder = SaleOrder::find($saleOrderId);
                                        if (!$saleOrder) return [];
                                        
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
                                        
                                        // Check which DOs are already invoiced
                                        $invoicedDOIds = Invoice::where('from_model_type', 'App\Models\SaleOrder')
                                            ->whereNotNull('delivery_orders')
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
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if (!$state || empty($state)) {
                                            $set('invoiceItem', []);
                                            $set('subtotal', 0);
                                            $set('total', 0);
                                            $set('other_fees', []);
                                            $set('dpp', 0);
                                            return;
                                        }
                                        
                                        $saleOrderId = $get('selected_sale_order');
                                        $customerId = $get('selected_customer');
                                        
                                        if (!$saleOrderId || !$customerId) return;
                                        
                                        $saleOrder = SaleOrder::with('customer')->find($saleOrderId);
                                        $deliveryOrders = DeliveryOrder::with('deliveryOrderItem.saleOrderItem')
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
                                                    $price = $item->saleOrderItem->unit_price - $item->saleOrderItem->discount + $item->saleOrderItem->tax;
                                                    $total = $price * $item->quantity;
                                                    
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
                                        
                                        // Calculate tax and total
                                        $tax = $get('tax') ?? 0;
                                        $otherFee = 0; // Initialize as 0
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $otherFee + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
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
                                    ->label('DPP')
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
                                    
                                Select::make('biaya_pengiriman_coa_id')
                                    ->label('COA Biaya Pengiriman')
                                    ->options(\App\Models\ChartOfAccount::all()->mapWithKeys(function ($coa) {
                                        return [$coa->id => "({$coa->code}) {$coa->name}"];
                                    }))
                                    ->searchable()
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '6100.02')->first()?->id;
                                    }),
                            ]),
                            
                        // Hidden fields
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
                    DeleteAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('invoice_date', 'desc');
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
