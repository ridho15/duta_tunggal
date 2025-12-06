<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseInvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
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
use Illuminate\Support\Facades\Auth;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Tables\Enums\ActionsPosition;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Invoice Pembelian';
    protected static ?string $modelLabel = 'Invoice Pembelian';
    protected static ?string $pluralModelLabel = 'Invoice Pembelian';
    protected static ?string $navigationGroup = 'Finance - Pembelian';
    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Form Invoice')
                    ->schema([
                        // Header Section - Sumber Invoice
                        Section::make('Sumber Invoice')
                            ->description('Silahkan Pilih Supplier')
                            ->columns(2)
                            ->schema([
                                Select::make('selected_supplier')
                                    ->label('Supplier')
                                    ->options(Supplier::all()->mapWithKeys(function ($supplier) {
                                        return [$supplier->id => "({$supplier->code}) {$supplier->name}"];
                                    }))
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Supplier harus dipilih'
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_purchase_order', null);
                                        $set('selected_purchase_receipts', []);
                                        $set('invoiceItem', []);
                                        $set('receiptBiayaItems', []);
                                        $set('subtotal', 0);
                                        $set('total', 0);
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
                                    
                                Select::make('selected_purchase_order')
                                    ->label('PO')
                                    ->options(function ($get) {
                                        $supplierId = $get('selected_supplier');
                                        if (!$supplierId) return [];
                                        
                                        return PurchaseOrder::where('supplier_id', $supplierId)
                                            ->where('status', 'completed')
                                            ->whereHas('purchaseReceipt', function ($query) {
                                                $query->where('status', 'completed');
                                            })
                                            ->get()
                                            ->filter(function ($po) {
                                                // Check if all receipts are invoiced
                                                $allReceiptIds = $po->purchaseReceipt()
                                                    ->where('status', 'completed')
                                                    ->pluck('id')
                                                    ->toArray();
                                                
                                                if (empty($allReceiptIds)) return false;
                                                
                                                $invoicedReceiptIds = Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
                                                    ->whereNotNull('purchase_receipts')
                                                    ->get()
                                                    ->pluck('purchase_receipts')
                                                    ->flatten()
                                                    ->intersect($allReceiptIds)
                                                    ->unique()
                                                    ->toArray();
                                                
                                                return count($invoicedReceiptIds) < count($allReceiptIds);
                                            })
                                            ->mapWithKeys(function ($po) {
                                                return [$po->id => $po->po_number];
                                            });
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_purchase_receipts', []);
                                        $set('invoiceItem', []);
                                        $set('receiptBiayaItems', []);
                                        $set('subtotal', 0);
                                        $set('total', 0);
                                        
                                        // Auto-set due date based on PO tempo_hutang
                                        if ($state) {
                                            $po = PurchaseOrder::find($state);
                                            if ($po && $po->tempo_hutang) {
                                                $invoiceDate = $get('invoice_date') ?: now();
                                                $dueDate = \Carbon\Carbon::parse($invoiceDate)->addDays($po->tempo_hutang);
                                                $set('due_date', $dueDate->toDateString());
                                            }
                                        }
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
                                    
                                DatePicker::make('invoice_date')
                                    ->label('Invoice Date')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Tanggal invoice harus diisi'
                                    ])
                                    ->default(now())
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        // Auto-update due date if PO is selected and has tempo_hutang
                                        $poId = $get('selected_purchase_order');
                                        if ($poId && $state) {
                                            $po = PurchaseOrder::find($poId);
                                            if ($po && $po->tempo_hutang) {
                                                $dueDate = \Carbon\Carbon::parse($state)->addDays($po->tempo_hutang);
                                                $set('due_date', $dueDate->toDateString());
                                            }
                                        }
                                        
                                        if ($state) {
                                            $set('due_date_display', $state);
                                        }
                                    }),
                                    
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

                        // Purchase Receipts Selection
                        Section::make('Silahkan Pilih Purchase Receipt')
                            ->schema([
                                Forms\Components\CheckboxList::make('selected_purchase_receipts')
                                    ->label('')
                                    ->options(function ($get) {
                                        $purchaseOrderId = $get('selected_purchase_order');
                                        if (!$purchaseOrderId) return [];
                                        
                                        $purchaseOrder = PurchaseOrder::find($purchaseOrderId);
                                        if (!$purchaseOrder) return [];
                                        
                                        // Get all receipts from this PO
                                        $purchaseReceipts = $purchaseOrder->purchaseReceipt()
                                            ->with('purchaseReceiptItem.purchaseOrderItem', 'purchaseReceiptBiaya')
                                            ->where('status', 'completed')
                                            ->get();
                                        
                                        // Check which receipts are already invoiced
                                        $invoicedReceiptIds = Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
                                            ->whereNotNull('purchase_receipts')
                                            ->get()
                                            ->pluck('purchase_receipts')
                                            ->flatten()
                                            ->unique()
                                            ->toArray();
                                        
                                        return $purchaseReceipts->mapWithKeys(function ($receipt) use ($invoicedReceiptIds) {
                                            $isInvoiced = in_array($receipt->id, $invoicedReceiptIds);
                                            
                                            // Calculate total from receipt items + biaya lainnya
                                            $total = $receipt->purchaseReceiptItem->sum(function ($item) {
                                                $purchaseOrderItem = $item->purchaseOrderItem;
                                                if ($purchaseOrderItem) {
                                                    // Calculate price after discount and tax (both are percentages)
                                                    $subtotal = $purchaseOrderItem->unit_price * $item->qty_accepted;
                                                    $discountAmount = $subtotal * ($purchaseOrderItem->discount / 100);
                                                    $afterDiscount = $subtotal - $discountAmount;
                                                    $taxAmount = $afterDiscount * ($purchaseOrderItem->tax / 100);
                                                    $finalPrice = $afterDiscount + $taxAmount;
                                                    return $finalPrice;
                                                }
                                                return 0;
                                            }) + $receipt->purchaseReceiptBiaya->sum('total');
                                            
                                            $label = "{$receipt->receipt_number} - Rp. " . number_format($total, 0, ',', '.');
                                            if ($isInvoiced) {
                                                $label .= " (Sudah di-invoice)";
                                            }
                                            return [$receipt->id => $label];
                                        })->toArray();
                                    })
                                    ->columns(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if (!$state || empty($state)) {
                                            $set('invoiceItem', []);
                                            $set('receiptBiayaItems', []);
                                            $set('subtotal', 0);
                                            
                                            // Recalculate total with manual other_fees only
                                            $otherFees = $get('other_fees') ?? [];
                                            $manualOtherFeeTotal = collect($otherFees)->sum('amount');
                                            $tax = $get('tax') ?? 0;
                                            $ppnRate = $get('ppn_rate') ?? 0;
                                            $finalTotal = $manualOtherFeeTotal + (0 * $tax / 100) + (0 * $ppnRate / 100);
                                            $set('total', $finalTotal);
                                            $set('other_fee', $manualOtherFeeTotal);
                                            return;
                                        }
                                        
                                        $purchaseOrderId = $get('selected_purchase_order');
                                        $supplierId = $get('selected_supplier');
                                        
                                        if (!$purchaseOrderId || !$supplierId) return;
                                        
                                        $purchaseOrder = PurchaseOrder::with('supplier')->find($purchaseOrderId);
                                        $purchaseReceipts = PurchaseReceipt::with('purchaseReceiptItem.purchaseOrderItem', 'purchaseReceiptBiaya')->whereIn('id', $state)->get();
                                        
                                        // Set supplier info
                                        $set('supplier_name', $purchaseOrder->supplier->name);
                                        $set('supplier_phone', $purchaseOrder->supplier->phone ?? '');
                                        $set('from_model_type', 'App\Models\PurchaseOrder');
                                        $set('from_model_id', $purchaseOrderId);
                                        
                                        // Calculate items from purchase receipts
                                        $items = [];
                                        $receiptBiayaItems = [];
                                        $subtotal = 0;
                                        
                                        foreach ($purchaseReceipts as $receipt) {
                                            foreach ($receipt->purchaseReceiptItem as $item) {
                                                $purchaseOrderItem = $purchaseOrder->purchaseOrderItem()
                                                    ->where('product_id', $item->product_id)
                                                    ->first();
                                                
                                                if ($purchaseOrderItem) {
                                                    // Calculate price after discount and tax (both are percentages)
                                                    $subtotal = $purchaseOrderItem->unit_price * $item->qty_accepted;
                                                    $discountAmount = $subtotal * ($purchaseOrderItem->discount / 100);
                                                    $afterDiscount = $subtotal - $discountAmount;
                                                    $taxAmount = $afterDiscount * ($purchaseOrderItem->tax / 100);
                                                    $price = $afterDiscount + $taxAmount;
                                                    $total = $price;
                                                    
                                                    $items[] = [
                                                        'product_id' => $item->product_id,
                                                        'quantity' => $item->qty_accepted,
                                                        'price' => $price,
                                                        'total' => $total
                                                    ];
                                                    
                                                    $subtotal += $total;
                                                }
                                            }
                                            
                                            // Add biaya lainnya from purchase receipt
                                            foreach ($receipt->purchaseReceiptBiaya as $biaya) {
                                                $receiptBiayaItems[] = [
                                                    'receipt_id' => $receipt->id,
                                                    'nama_biaya' => $biaya->nama_biaya,
                                                    'total' => $biaya->total,
                                                ];
                                            }
                                        }
                                        
                                        $set('invoiceItem', $items);
                                        $set('subtotal', $subtotal);
                                        $set('dpp', $subtotal);
                                        $set('purchase_receipts', $state);
                                        
                                        // Update receiptBiayaItems: merge with existing, add new, remove unselected
                                        $existingBiaya = $get('receiptBiayaItems') ?? [];
                                        $updatedBiaya = collect($existingBiaya)->filter(function ($biaya) use ($state) {
                                            return in_array($biaya['receipt_id'], $state);
                                        })->toArray();
                                        
                                        // Add biaya from newly selected receipts if not already present
                                        foreach ($purchaseReceipts as $receipt) {
                                            $hasBiaya = collect($updatedBiaya)->contains('receipt_id', $receipt->id);
                                            if (!$hasBiaya) {
                                                foreach ($receipt->purchaseReceiptBiaya as $biaya) {
                                                    $updatedBiaya[] = [
                                                        'receipt_id' => $receipt->id,
                                                        'nama_biaya' => $biaya->nama_biaya,
                                                        'total' => $biaya->total,
                                                    ];
                                                }
                                            }
                                        }
                                        
                                        $set('receiptBiayaItems', $updatedBiaya);
                                        
                                        // Calculate total including receipt biaya and manual other_fees
                                        $existingOtherFees = $get('other_fees') ?? [];
                                        $receiptBiayaTotal = collect($updatedBiaya)->sum('total');
                                        $manualOtherFeeTotal = collect($existingOtherFees)->sum('amount');
                                        $totalOtherFee = $receiptBiayaTotal + $manualOtherFeeTotal;
                                        
                                        // Calculate tax and total
                                        $tax = $get('tax') ?? 0;
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $totalOtherFee + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);

                                        // Update tax and PPN amount displays
                                        $taxAmount = $subtotal * $tax / 100;
                                        $set('tax_amount', $taxAmount);
                                        $ppnAmount = $subtotal * $ppnRate / 100;
                                        $set('ppn_amount', $ppnAmount);
                                    }),
                            ]),

                        // Invoice Items Section
                        Section::make('Item Invoice')
                            ->description('Item yang akan di-invoice berdasarkan Purchase Receipt yang dipilih')
                            ->schema([
                                Repeater::make('invoiceItem')
                                    ->label('')
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Produk')
                                            ->options(\App\Models\Product::all()->mapWithKeys(function ($product) {
                                                return [$product->id => $product->name];
                                            }))
                                            ->searchable()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Produk harus dipilih'
                                            ])
                                            ->disabled(),
                                        TextInput::make('quantity')
                                            ->label('Qty')
                                            ->numeric()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Qty tidak boleh kosong',
                                                'numeric' => 'Qty harus berupa angka'
                                            ])
                                            ->disabled(),
                                        TextInput::make('price')
                                            ->label('Harga')
                                            ->indonesianMoney()
                                            ->numeric()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Harga tidak boleh kosong',
                                                'numeric' => 'Harga harus berupa angka'
                                            ])
                                            ->disabled(),
                                        TextInput::make('total')
                                            ->label('Total')
                                            ->indonesianMoney()
                                            ->numeric()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Total tidak boleh kosong',
                                                'numeric' => 'Total harus berupa angka'
                                            ])
                                            ->disabled(),
                                    ])
                                    ->columns(4)
                                    ->disableItemCreation()
                                    ->disableItemDeletion()
                                    ->disableItemMovement(),
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
                                        $manualOtherFeeTotal = collect($state ?? [])->sum('amount');
                                        $set('other_fee', $manualOtherFeeTotal);
                                        
                                        // Include receipt biaya in total calculation
                                        $receiptBiayaItems = $get('receiptBiayaItems') ?? [];
                                        $receiptBiayaTotal = collect($receiptBiayaItems)->sum('total');
                                        $totalOtherFee = $manualOtherFeeTotal + $receiptBiayaTotal;
                                        
                                        $subtotal = $get('subtotal') ?? 0;
                                        $tax = $get('tax') ?? 0;
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $totalOtherFee + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);

                                        // Update tax and PPN amount displays
                                        $taxAmount = $subtotal * $tax / 100;
                                        $set('tax_amount', $taxAmount);
                                        $ppnAmount = $subtotal * $ppnRate / 100;
                                        $set('ppn_amount', $ppnAmount);
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
                                        $subtotal = $get('subtotal') ?? 0;
                                        $otherFees = $get('other_fees') ?? [];
                                        $manualOtherFeeTotal = collect($otherFees)->sum('amount');

                                        // Include receipt biaya in total calculation
                                        $receiptBiayaItems = $get('receiptBiayaItems') ?? [];
                                        $receiptBiayaTotal = collect($receiptBiayaItems)->sum('total');
                                        $totalOtherFee = $manualOtherFeeTotal + $receiptBiayaTotal;

                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $totalOtherFee + ($subtotal * $state / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                        $set('other_fee', $totalOtherFee);

                                        // Update tax amount display
                                        $taxAmount = $subtotal * $state / 100;
                                        $set('tax_amount', $taxAmount);

                                        // Update PPN amount display
                                        $ppnAmount = $subtotal * $ppnRate / 100;
                                        $set('ppn_amount', $ppnAmount);
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
                                        return $taxSetting?->rate ?? 0;
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $subtotal = $get('subtotal') ?? 0;
                                        $otherFees = $get('other_fees') ?? [];
                                        $manualOtherFeeTotal = collect($otherFees)->sum('amount');

                                        // Include receipt biaya in total calculation
                                        $receiptBiayaItems = $get('receiptBiayaItems') ?? [];
                                        $receiptBiayaTotal = collect($receiptBiayaItems)->sum('total');
                                        $totalOtherFee = $manualOtherFeeTotal + $receiptBiayaTotal;

                                        $tax = $get('tax') ?? 0;
                                        $finalTotal = $subtotal + $totalOtherFee + ($subtotal * $tax / 100) + ($subtotal * $state / 100);
                                        $set('total', $finalTotal);
                                        $set('other_fee', $totalOtherFee);

                                        // Update tax amount display
                                        $taxAmount = $subtotal * $tax / 100;
                                        $set('tax_amount', $taxAmount);

                                        // Update PPN amount display
                                        $ppnAmount = $subtotal * $state / 100;
                                        $set('ppn_amount', $ppnAmount);
                                    }),

                                TextInput::make('tax_amount')
                                    ->label('Nilai Tax (Rp)')
                                    ->indonesianMoney()
                                    ->numeric()
                                    ->readonly()
                                    ->placeholder('0'),

                                TextInput::make('ppn_amount')
                                    ->label('Nilai PPN (Rp)')
                                    ->indonesianMoney()
                                    ->numeric()
                                    ->readonly()
                                    ->placeholder('0'),
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
                            
                        // Status Invoice
                        Section::make('Status Invoice')
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'sent' => 'Sent',
                                        'paid' => 'Paid',
                                        'partially_paid' => 'Partially Paid',
                                        'overdue' => 'Overdue',
                                    ])
                                    ->default('draft')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Status harus dipilih'
                                    ]),
                            ]),

                        // COA Selection
                        Section::make('Pemilihan COA (Chart of Account)')
                            ->description('Pilih COA yang sesuai untuk pencatatan journal entry')
                            ->columns(2)
                            ->schema([
                                Select::make('accounts_payable_coa_id')
                                    ->label('COA Hutang Supplier (Accounts Payable)')
                                    ->options(\App\Models\ChartOfAccount::where('type', 'liability')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => $coa->formatted_name];
                                    }))
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'COA hutang supplier harus dipilih'
                                    ])
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '2110')->first()?->id;
                                    }),

                                Select::make('ppn_masukan_coa_id')
                                    ->label('COA PPn Masukan')
                                    ->options(\App\Models\ChartOfAccount::where('type', 'asset')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => $coa->formatted_name];
                                    }))
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '1170.06')->first()?->id;
                                    }),

                                Select::make('inventory_coa_id')
                                    ->label('COA Inventory')
                                    ->options(\App\Models\ChartOfAccount::where('type', 'asset')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => $coa->formatted_name];
                                    }))
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '1140.01')->first()?->id;
                                    }),

                                Select::make('expense_coa_id')
                                    ->label('COA Biaya Lain (Opsional)')
                                    ->options(\App\Models\ChartOfAccount::where('type', 'expense')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => $coa->formatted_name];
                                    }))
                                    ->searchable(['code', 'name'])
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '6100.02')->first()?->id;
                                    })
                                    ->helperText('COA ini digunakan jika ada biaya yang tidak memiliki COA di PO'),
                            ]),
                            
                        // Hidden fields
                        Hidden::make('from_model_type')->default('App\Models\PurchaseOrder'),
                        Hidden::make('from_model_id'),
                        Hidden::make('supplier_name'),
                        Hidden::make('supplier_phone'),
                        Hidden::make('subtotal'),
                        Hidden::make('purchase_receipts'),
                        Hidden::make('receiptBiayaItems'),
                        
                        // Biaya Lain dari Purchase Receipt
                        Repeater::make('receiptBiayaItems')
                            ->label('Biaya Lain dari Purchase Receipt')
                            ->schema([
                                Hidden::make('receipt_id'),
                                TextInput::make('nama_biaya')
                                    ->label('Nama Biaya')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Nama biaya tidak boleh kosong'
                                    ]),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->indonesianMoney()
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
                            ->afterStateUpdated(function ($set, $get, $state) {
                                // Recalculate total when receiptBiayaItems changes
                                $subtotal = $get('subtotal') ?? 0;
                                $receiptBiayaItems = $get('receiptBiayaItems') ?? [];
                                $receiptBiayaTotal = collect($receiptBiayaItems)->sum('total');
                                $existingOtherFees = $get('other_fees') ?? [];
                                $manualOtherFeeTotal = collect($existingOtherFees)->sum('amount');
                                $totalOtherFee = $receiptBiayaTotal + $manualOtherFeeTotal;
                                
                                // Calculate tax and total
                                $tax = $get('tax') ?? 0;
                                $ppnRate = $get('ppn_rate') ?? 0;
                                $finalTotal = $subtotal + $totalOtherFee + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
                                $set('total', $finalTotal);

                                // Update tax and PPN amount displays
                                $taxAmount = $subtotal * $tax / 100;
                                $set('tax_amount', $taxAmount);
                                $ppnAmount = $subtotal * $ppnRate / 100;
                                $set('ppn_amount', $ppnAmount);
                            }),
                    ])
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Purchase Order Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('fromModel.po_number')
                            ->label('PO Number'),
                        Infolists\Components\TextEntry::make('fromModel.supplier.name')
                            ->label('Supplier'),
                        Infolists\Components\TextEntry::make('fromModel.supplier.phone')
                            ->label('Supplier Phone'),
                        Infolists\Components\TextEntry::make('fromModel.order_date')
                            ->label('PO Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('purchase_receipts_display')
                            ->label('Purchase Receipts')
                            ->listWithLineBreaks()
                            ->state(function (Invoice $record) {
                                if (!$record->purchase_receipts) return 'No receipts';

                                $receipts = \App\Models\PurchaseReceipt::whereIn('id', $record->purchase_receipts)
                                    ->get()
                                    ->map(function ($receipt) {
                                        return $receipt->receipt_number . ' (' . \Carbon\Carbon::parse($receipt->receipt_date)->format('d/m/Y') . ')';
                                    })
                                    ->toArray();

                                return $receipts;
                            }),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Invoice Items')
                    ->schema([
                        Infolists\Components\TextEntry::make('invoice_items_display')
                            ->label('Items')
                            ->listWithLineBreaks()
                            ->state(function (Invoice $record) {
                                $items = [];
                                foreach ($record->invoiceItem as $item) {
                                    $product = $item->product;
                                    $productName = $product ? $product->name : 'Unknown Product';
                                    $items[] = sprintf(
                                        '%s: %s x Rp %s = Rp %s',
                                        $productName,
                                        number_format($item->quantity, 2, ',', '.'),
                                        number_format($item->price, 0, ',', '.'),
                                        number_format($item->total, 0, ',', '.')
                                    );
                                }
                                return $items;
                            }),
                    ]),

                Infolists\Components\Section::make('Invoice Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('invoice_number')
                            ->label('Invoice Number'),
                        Infolists\Components\TextEntry::make('invoice_date')
                            ->label('Invoice Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('due_date')
                            ->label('Due Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->money('IDR'),
                        Infolists\Components\TextEntry::make('tax')
                            ->label('Tax (%)')
                            ->state(function (Invoice $record) {
                                return $record->tax ? $record->tax . '%' : '0%';
                            }),
                        Infolists\Components\TextEntry::make('ppn_rate')
                            ->label('PPN Rate (%)')
                            ->state(function (Invoice $record) {
                                return $record->ppn_rate ? $record->ppn_rate . '%' : '0%';
                            }),
                        Infolists\Components\TextEntry::make('dpp')
                            ->label('DPP')
                            ->money('IDR'),
                        Infolists\Components\TextEntry::make('other_fee_total')
                            ->label('Other Fees')
                            ->money('IDR')
                            ->state(function (Invoice $record) {
                                return $record->getOtherFeeTotalAttribute();
                            }),
                        Infolists\Components\TextEntry::make('total')
                            ->label('Invoice Total')
                            ->money('IDR'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Chart of Accounts')
                    ->schema([
                        Infolists\Components\TextEntry::make('accountsPayableCoa.formatted_name')
                            ->label('Accounts Payable COA'),
                        Infolists\Components\TextEntry::make('ppnMasukanCoa.formatted_name')
                            ->label('PPN Masukan COA'),
                        Infolists\Components\TextEntry::make('inventoryCoa.formatted_name')
                            ->label('Inventory COA'),
                        Infolists\Components\TextEntry::make('expenseCoa.formatted_name')
                            ->label('Expense COA')
                            ->placeholder('Not set'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function modifyInfolistQueryUsing(Builder $query): Builder
    {
        return $query->with([
            'fromModel.purchaseOrderItem.purchaseReceiptItem',
            'fromModel.supplier',
            'invoiceItem.product',
            'accountsPayableCoa',
            'ppnMasukanCoa',
            'inventoryCoa',
            'expenseCoa'
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query->where('from_model_type', 'App\Models\PurchaseOrder');
            })
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('cabang')
                    ->label('Cabang')
                    ->formatStateUsing(function ($state) {
                        return "({$state->kode}) {$state->nama}";
                    })
                    ->searchable(query: function (Builder $query, $search) {
                        return $query->whereHas('cabang', function ($query) use ($search) {
                            return $query->where('kode', 'LIKE', '%' . $search . '%')
                                ->orWhere('nama', 'LIKE', '%' . $search . '%');
                        });
                    }),
                    
                TextColumn::make('supplier_name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('supplier_phone')
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
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'draft' => 'Draft',
                            'sent' => 'Terkirim',
                            'paid' => 'Lunas',
                            'partially_paid' => 'Dibayar Sebagian',
                            'overdue' => 'Terlambat',
                            default => $state,
                        };
                    })
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'sent',
                        'success' => 'paid',
                        'primary' => 'partially_paid',
                        'danger' => 'overdue',
                    ]),
            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    \Filament\Tables\Actions\Action::make('mark_as_sent')
                        ->label('Mark as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === 'draft')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Invoice as Sent')
                        ->modalDescription('Are you sure you want to mark this invoice as sent? This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, Mark as Sent')
                        ->action(function ($record) {
                            $record->update(['status' => 'sent']);
                            \Filament\Notifications\Notification::make()
                                ->title('Invoice marked as sent')
                                ->success()
                                ->send();
                        }),
                ])
                    ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('invoice_date', 'desc');
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        // Calculate tax and PPN amounts for display
        $subtotal = $data['subtotal'] ?? 0;
        $tax = $data['tax'] ?? 0;
        $ppnRate = $data['ppn_rate'] ?? 0;

        $data['tax_amount'] = $subtotal * $tax / 100;
        $data['ppn_amount'] = $subtotal * $ppnRate / 100;

        return $data;
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        return static::prepareInvoiceData($data);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        return static::prepareInvoiceData($data);
    }

    protected static function prepareInvoiceData(array $data): array
    {
        // Remove form-specific fields and prepare data for database
        unset($data['selected_supplier'], $data['selected_purchase_order'], $data['selected_purchase_receipts']);
        
        // Ensure other_fee is properly formatted - combine manual fees and receipt fees
        $otherFees = [];
        if (isset($data['other_fees']) && is_array($data['other_fees'])) {
            $otherFees = array_merge($otherFees, $data['other_fees']);
        }
        if (isset($data['receiptBiayaItems']) && is_array($data['receiptBiayaItems'])) {
            $otherFees = array_merge($otherFees, $data['receiptBiayaItems']);
        }
        $data['other_fee'] = collect($otherFees)->map(function ($fee) {
            return [
                'name' => $fee['nama_biaya'] ?? $fee['name'] ?? 'Biaya Lain',
                'amount' => (float) ($fee['total'] ?? $fee['amount'] ?? 0),
            ];
        })->toArray();
        
        // Remove temporary fields
        unset($data['other_fees'], $data['receiptBiayaItems']);
        
        // Calculate totals if not set
        if (!isset($data['total']) || $data['total'] == 0) {
            $subtotal = $data['subtotal'] ?? 0;
            $otherFeeTotal = collect($data['other_fee'] ?? [])->sum('amount');
            $tax = $data['tax'] ?? 0;
            $ppnRate = $data['ppn_rate'] ?? 0;
            $data['total'] = $subtotal + $otherFeeTotal + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
        }
        
        return $data;
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
            'index' => Pages\ListPurchaseInvoices::route('/'),
            'create' => Pages\CreatePurchaseInvoice::route('/create'),
            'view' => Pages\ViewPurchaseInvoice::route('/{record}'),
            'edit' => Pages\EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }
}
