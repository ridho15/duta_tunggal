<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseInvoiceResource\Pages;
use App\Http\Controllers\HelperController;
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
use Filament\Forms\Components\Placeholder;
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
use Filament\Infolists\Components\ViewEntry;
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
                                        return [$supplier->id => "({$supplier->code}) {$supplier->perusahaan}"];
                                    }))
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Supplier harus dipilih'
                                    ])
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_order_request', null);
                                        $set('selected_purchase_orders', []);
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
                                    ->helperText('Diisi otomatis dari PO/Receipt yang dipilih. Dapat diubah bila perlu.')
                                    ->validationMessages([
                                        'required' => 'Cabang harus dipilih'
                                    ]),
                                    
                                // Task 14: Select Order Request to filter POs
                                Select::make('selected_order_request')
                                    ->label('Order Request (OR)')
                                    ->options(function ($get) {
                                        $supplierId = $get('selected_supplier');
                                        if (!$supplierId) return [];
                                        
                                        return \App\Models\OrderRequest::where(function ($q) use ($supplierId) {
                                                                                        // Match ORs by item-level supplier OR ORs that have POs from this supplier
                                                                                        $q->whereHas('orderRequestItem', fn($iq) => $iq->where('supplier_id', $supplierId))
                                                                                            ->orWhereHas('purchaseOrders', fn($pq) => $pq->where('supplier_id', $supplierId));
                                        })
                                            ->whereHas('purchaseOrder', function ($q) {
                                                $q->where('status', 'completed')
                                                  ->whereHas('purchaseReceipt', fn($q2) => $q2->where('status', 'completed'));
                                            })
                                            ->orderByDesc('request_date')
                                            ->get()
                                            ->mapWithKeys(fn ($or) => [$or->id => $or->request_number]);
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->nullable()
                                    ->helperText('Pilih OR untuk memfilter PO. Biarkan kosong untuk melihat semua PO.')
                                    ->afterStateUpdated(function ($set) {
                                        $set('selected_purchase_orders', []);
                                        $set('selected_purchase_receipts', []);
                                        $set('invoiceItem', []);
                                        $set('receiptBiayaItems', []);
                                        $set('subtotal', 0);
                                        $set('total', 0);
                                    }),

                                // Task 14: Multiple PO selection filtered by OR
                                Forms\Components\CheckboxList::make('selected_purchase_orders')
                                    ->label('Purchase Orders')
                                    ->options(function ($get) {
                                        $supplierId = $get('selected_supplier');
                                        if (!$supplierId) return [];
                                        
                                        $query = PurchaseOrder::where('supplier_id', $supplierId)
                                            ->where('status', 'completed')
                                            ->whereHas('purchaseReceipt', fn($q) => $q->where('status', 'completed'));
                                        
                                        // Filter by OR if selected
                                        $orId = $get('selected_order_request');
                                        if ($orId) {
                                            $query->where('refer_model_type', 'App\Models\OrderRequest')
                                                  ->where('refer_model_id', $orId);
                                        }
                                        
                                        return $query->get()
                                            ->mapWithKeys(function ($po) {
                                                $allReceiptIds = $po->purchaseReceipt()
                                                    ->where('status', 'completed')
                                                    ->pluck('id')->toArray();
                                                
                                                $invoicedReceiptIds = Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
                                                    ->whereNotNull('purchase_receipts')
                                                    ->get()->pluck('purchase_receipts')->flatten()
                                                    ->intersect($allReceiptIds)->unique()->toArray();
                                                
                                                $fullyInvoiced = !empty($allReceiptIds) && count($invoicedReceiptIds) >= count($allReceiptIds);
                                                $label = $po->po_number . ($po->referModel?->request_number ? ' (OR: ' . $po->referModel->request_number . ')' : '');
                                                if ($fullyInvoiced) $label .= ' [Sudah di-invoice]';
                                                
                                                return [$po->id => $label];
                                            });
                                    })
                                    ->disableOptionWhen(function ($value, $get) {
                                        $supplierId = $get('selected_supplier');
                                        if (!$supplierId) {
                                            return false;
                                        }

                                        $po = PurchaseOrder::where('supplier_id', $supplierId)
                                            ->where('status', 'completed')
                                            ->find($value);

                                        if (!$po) {
                                            return false;
                                        }

                                        $allReceiptIds = $po->purchaseReceipt()
                                            ->where('status', 'completed')
                                            ->pluck('id')
                                            ->toArray();

                                        if (empty($allReceiptIds)) {
                                            return true;
                                        }

                                        $invoicedReceiptIds = Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
                                            ->whereNotNull('purchase_receipts')
                                            ->get()
                                            ->pluck('purchase_receipts')
                                            ->flatten()
                                            ->intersect($allReceiptIds)
                                            ->unique()
                                            ->toArray();

                                        return count($invoicedReceiptIds) >= count($allReceiptIds);
                                    })
                                    ->columns(2)
                                    ->bulkToggleable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_purchase_receipts', []);
                                        $set('invoiceItem', []);
                                        $set('receiptBiayaItems', []);
                                        $set('subtotal', 0);
                                        $set('total', 0);
                                        
                                        // Auto-set due date based on first PO tempo_hutang
                                        if ($state && count($state) > 0) {
                                            $po = PurchaseOrder::find($state[0]);
                                            if ($po && $po->tempo_hutang) {
                                                $invoiceDate = $get('invoice_date') ?: now();
                                                $dueDate = \Carbon\Carbon::parse($invoiceDate)->addDays($po->tempo_hutang);
                                                $set('due_date', $dueDate->toDateString());
                                            }

                                            if ($po && $po->cabang_id) {
                                                $set('cabang_id', $po->cabang_id);
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
                                    ->unique(table: 'invoices', column: 'invoice_number', ignoreRecord: true)
                                    ->validationMessages([
                                        'required' => 'Nomor invoice tidak boleh kosong',
                                        'max' => 'Nomor invoice terlalu panjang',
                                        'unique' => 'Nomor invoice sudah digunakan'
                                    ])
                                    ->suffixAction(
                                        Action::make('generate')
                                            ->icon('heroicon-m-arrow-path')
                                            ->tooltip('Generate Invoice Number')
                                            ->action(function ($set, $get) {
                                                $invoiceService = app(InvoiceService::class);
                                                $set('invoice_number', $invoiceService->generatePurchaseInvoiceNumber());
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
                                        // Auto-update due date based on first selected PO tempo_hutang
                                        $poIds = $get('selected_purchase_orders');
                                        if ($poIds && count($poIds) > 0 && $state) {
                                            $po = PurchaseOrder::find($poIds[0]);
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
                                Placeholder::make('receipt_invoiced_info')
                                    ->label('')
                                    ->content('Receipt yang berlabel "Sudah di-invoice" tetap ditampilkan, namun tidak dapat dipilih.'),
                                // Task 14: Receipts from ALL selected POs
                                Forms\Components\CheckboxList::make('selected_purchase_receipts')
                                    ->label('')
                                    ->options(function ($get) {
                                        $purchaseOrderIds = $get('selected_purchase_orders');
                                        if (!$purchaseOrderIds || empty($purchaseOrderIds)) return [];
                                        
                                        $purchaseOrders = PurchaseOrder::with('purchaseOrderItem')
                                            ->whereIn('id', $purchaseOrderIds)->get()->keyBy('id');
                                        if ($purchaseOrders->isEmpty()) return [];
                                        
                                        // Check which receipts are already invoiced
                                        $invoicedReceiptIds = Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
                                            ->whereNotNull('purchase_receipts')
                                            ->get()->pluck('purchase_receipts')->flatten()->unique()->toArray();
                                        
                                        $options = [];
                                        foreach ($purchaseOrders as $purchaseOrder) {
                                            $purchaseReceipts = $purchaseOrder->purchaseReceipt()
                                                ->with('purchaseReceiptItem.purchaseOrderItem', 'purchaseReceiptBiaya')
                                                ->where('status', 'completed')
                                                ->get();
                                            
                                            foreach ($purchaseReceipts as $receipt) {
                                                $isInvoiced = in_array($receipt->id, $invoicedReceiptIds);
                                                
                                                $total = $receipt->purchaseReceiptItem->sum(function ($item) use ($purchaseOrder) {
                                                    $purchaseOrderItem = $purchaseOrder->purchaseOrderItem
                                                        ->firstWhere('product_id', $item->product_id);
                                                    if ($purchaseOrderItem) {
                                                        $subtotal = $purchaseOrderItem->unit_price * $item->qty_accepted;
                                                        $discountAmount = $subtotal * ($purchaseOrderItem->discount / 100);
                                                        return $subtotal - $discountAmount;
                                                    }
                                                    return 0;
                                                }) + $receipt->purchaseReceiptBiaya->sum(fn ($biaya) => (float) \App\Helpers\MoneyHelper::parse($biaya->total ?? 0));
                                                
                                                $label = "[{$purchaseOrder->po_number}] {$receipt->receipt_number} - Rp. " . number_format($total, 0, ',', '.');
                                                if ($isInvoiced) $label .= ' (Sudah di-invoice)';
                                                $options[$receipt->id] = $label;
                                            }
                                        }
                                        return $options;
                                    })
                                    ->disableOptionWhen(function ($value) {
                                        $invoicedReceiptIds = Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
                                            ->whereNotNull('purchase_receipts')
                                            ->get()
                                            ->pluck('purchase_receipts')
                                            ->flatten()
                                            ->unique()
                                            ->map(fn ($id) => (int) $id)
                                            ->toArray();

                                        return in_array((int) $value, $invoicedReceiptIds, true);
                                    })
                                    ->columns(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        if (!$state || empty($state)) {
                                            $set('invoiceItem', []);
                                            $set('receiptBiayaItems', []);
                                            $set('subtotal', 0);
                                            $set('dpp', 0);
                                            $set('tax', 0);
                                            $set('ppn_amount', 0);
                                            
                                            // Recalculate total with manual other_fees only
                                            $otherFees = $get('other_fees') ?? [];
                                            $manualOtherFeeTotal = (float) collect($otherFees)->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::parse($fee['amount'] ?? 0));
                                            $finalTotal = $manualOtherFeeTotal;
                                            $set('total', $finalTotal);
                                            $set('other_fee', $manualOtherFeeTotal);
                                            return;
                                        }
                                        
                                        $purchaseOrderIds = $get('selected_purchase_orders');
                                        $supplierId = $get('selected_supplier');
                                        
                                        if (!$purchaseOrderIds || empty($purchaseOrderIds) || !$supplierId) return;
                                        
                                        // Task 14: Load ALL selected POs, keyed by ID for quick lookup
                                        $purchaseOrders = PurchaseOrder::with('supplier', 'purchaseOrderItem')
                                            ->whereIn('id', $purchaseOrderIds)->get()->keyBy('id');
                                        
                                        if ($purchaseOrders->isEmpty()) return;
                                        
                                        $purchaseReceipts = PurchaseReceipt::with('purchaseReceiptItem', 'purchaseReceiptBiaya')->whereIn('id', $state)->get();

                                        // Set supplier info from first PO
                                        $firstPo = $purchaseOrders->first();

                                        // D1: keep cabang aligned with selected receipt/PO chain
                                        if ($purchaseReceipts->isNotEmpty() && $purchaseReceipts->first()->cabang_id) {
                                            $set('cabang_id', $purchaseReceipts->first()->cabang_id);
                                        } elseif ($firstPo && $firstPo->cabang_id) {
                                            $set('cabang_id', $firstPo->cabang_id);
                                        }

                                        $set('supplier_name', $firstPo->supplier->perusahaan);
                                        $set('supplier_phone', $firstPo->supplier->phone ?? '');
                                        $set('from_model_type', 'App\Models\PurchaseOrder');
                                        $set('from_model_id', $firstPo->id);
                                        $set('purchase_order_ids', $purchaseOrderIds);
                                        
                                        // Calculate items from purchase receipts
                                        $items = [];
                                        $receiptBiayaItems = [];
                                        $subtotal = 0;
                                        $taxAmount = 0;
                                        
                                        foreach ($purchaseReceipts as $receipt) {
                                            // Task 14: Find the correct PO for this receipt
                                            $receiptPo = $purchaseOrders->get($receipt->purchase_order_id) ?? $firstPo;
                                            
                                            foreach ($receipt->purchaseReceiptItem as $item) {
                                                $purchaseOrderItem = $receiptPo->purchaseOrderItem
                                                    ->firstWhere('product_id', $item->product_id);
                                                
                                                if ($purchaseOrderItem) {
                                                    // B2: DPP must be pre-tax base to avoid double PPN
                                                    // 1) apply discount
                                                    // 2) if PO item is Inklusif, extract tax portion from price
                                                    $unitPrice = (float) ($purchaseOrderItem->unit_price ?? 0);
                                                    $discountPct = (float) ($purchaseOrderItem->discount ?? 0);
                                                    $afterDiscount = $unitPrice * (1 - ($discountPct / 100));

                                                    $taxRate = (float) ($purchaseOrderItem->tax ?? 0);
                                                    $tipePajak = strtolower(trim((string) ($purchaseOrderItem->tipe_pajak ?? 'Eklusif')));
                                                    $isInclusive = in_array($tipePajak, ['inklusif', 'inclusive'], true);

                                                    $dppUnitPrice = $afterDiscount;
                                                    if ($isInclusive && $taxRate > 0) {
                                                        $dppUnitPrice = $afterDiscount / (1 + ($taxRate / 100));
                                                    }

                                                    $total = $dppUnitPrice * (float) ($item->qty_accepted ?? 0);
                                                    $itemTaxAmount = $total * ($taxRate / 100);
                                                    
                                                    $items[] = [
                                                        'product_id' => $item->product_id,
                                                        'quantity' => $item->qty_accepted,
                                                        'price' => $dppUnitPrice,
                                                        'total' => $total
                                                    ];
                                                    
                                                    $subtotal += $total; // Accumulate DPP (pre-tax subtotal)
                                                    $taxAmount += $itemTaxAmount;
                                                }
                                            }
                                            
                                            // Add biaya lainnya from purchase receipt
                                            foreach ($receipt->purchaseReceiptBiaya as $biaya) {
                                                // B2: Do NOT mix receipt biaya into DPP.
                                                // All receipt biaya are handled as other fees to avoid tax double-counting.
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
                                        $receiptBiayaTotal = (float) collect($updatedBiaya)->sum(fn ($row) => (float) \App\Helpers\MoneyHelper::parse($row['total'] ?? 0));
                                        $manualOtherFeeTotal = (float) collect($existingOtherFees)->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::parse($fee['amount'] ?? 0));
                                        $totalOtherFee = $receiptBiayaTotal + $manualOtherFeeTotal;
                                        $effectivePpnRate = $subtotal > 0 ? ($taxAmount / $subtotal) * 100 : 0;
                                        
                                        // Calculate total using tax amount derived from PO item tax
                                        $finalTotal = $subtotal + $totalOtherFee + $taxAmount;
                                        $set('total', $finalTotal);
                                        $set('tax', $taxAmount);
                                        $set('ppn_rate', $effectivePpnRate);

                                        // Update PPN amount display
                                        $set('ppn_amount', $taxAmount);
                                    }),
                            ]),

                        // Invoice Items Section
                        Section::make('Item Invoice')
                            ->description('Item yang akan di-invoice berdasarkan Purchase Receipt yang dipilih')
                            ->schema([
                                Placeholder::make('invoice_item_readonly_info')
                                    ->label('')
                                    ->content('Harga mengikuti Purchase Receipt / Purchase Order dan tidak dapat diubah manual.'),
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
                                            ->disabled()
                                            ->dehydrated(true),
                                        TextInput::make('quantity')
                                            ->label('Qty')
                                            ->numeric()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Qty tidak boleh kosong',
                                                'numeric' => 'Qty harus berupa angka'
                                            ])
                                            ->disabled()
                                            ->dehydrated(true),
                                        TextInput::make('price')
                                            ->label('Harga')
                                            ->indonesianMoney()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Harga tidak boleh kosong',
                                            ])
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->readOnly(),
                                        TextInput::make('total')
                                            ->label('Total')
                                            ->indonesianMoney()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Total tidak boleh kosong',
                                            ])
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->readOnly(),
                                    ])
                                    ->columns(4)
                                    ->disableItemCreation()
                                    ->disableItemDeletion()
                                    ->disableItemMovement()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        // Recalculate subtotal when invoice items change (manual editing)
                                        // Note: DPP is also calculated in selected_purchase_receipts callback
                                        // This callback allows manual override of calculated values
                                        $subtotal = 0;
                                        if (is_array($state)) {
                                            foreach ($state as $item) {
                                                $quantity = $item['quantity'] ?? 0;
                                                $price = HelperController::parseIndonesianMoney($item['price'] ?? 0);
                                                $itemTotal = $quantity * $price;
                                                $subtotal += $itemTotal;
                                            }
                                        }
                                        $set('subtotal', $subtotal);
                                        $set('dpp', $subtotal);
                                        // Recalculate total with other fees using PPN only
                                        $otherFees = $get('other_fees') ?? [];
                                        $manualOtherFeeTotal = (float) collect($otherFees)->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::parse($fee['amount'] ?? 0));
                                        $receiptBiayaItems = $get('receiptBiayaItems') ?? [];
                                        $receiptBiayaTotal = (float) collect($receiptBiayaItems)->sum(fn ($row) => (float) \App\Helpers\MoneyHelper::parse($row['total'] ?? 0));
                                        $totalOtherFee = $manualOtherFeeTotal + $receiptBiayaTotal;
                                        
                                        $ppnRate = (float) ($get('ppn_rate') ?? 0);
                                        $taxAmount = $subtotal * $ppnRate / 100;
                                        $finalTotal = $subtotal + $totalOtherFee + $taxAmount;
                                        $set('total', $finalTotal);
                                        $set('tax', $taxAmount);
                                        
                                        // Update PPN amount display
                                        $set('ppn_amount', $taxAmount);
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
                                            ->default('Biaya Lain')
                                            ->disabled(fn ($operation) => $operation === 'edit'),
                                        TextInput::make('amount')
                                            ->label('Jumlah')
                                            ->indonesianMoney()
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Jumlah tidak boleh kosong',
                                            ])
                                            ->default(0)
                                            ->reactive()
                                            ->disabled(fn ($operation) => $operation === 'edit')
                                            ->dehydrated(true),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->disableItemCreation(fn ($operation) => $operation === 'edit')
                                    ->disableItemDeletion(fn ($operation) => $operation === 'edit')
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $manualOtherFeeTotal = (float) collect($state ?? [])->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::parse($fee['amount'] ?? 0));
                                        $set('other_fee', $manualOtherFeeTotal);
                                        
                                        // Include receipt biaya in total calculation
                                        $receiptBiayaItems = $get('receiptBiayaItems') ?? [];
                                        $receiptBiayaTotal = (float) collect($receiptBiayaItems)->sum(fn ($row) => (float) \App\Helpers\MoneyHelper::parse($row['total'] ?? 0));
                                        $totalOtherFee = $manualOtherFeeTotal + $receiptBiayaTotal;
                                        
                                        $subtotal = (float) \App\Helpers\MoneyHelper::parse($get('subtotal') ?? 0);
                                        $taxAmount = (float) \App\Helpers\MoneyHelper::parse($get('tax') ?? 0);
                                        $finalTotal = $subtotal + $totalOtherFee + $taxAmount;
                                        $set('total', $finalTotal);

                                        // Update PPN amount display
                                        $set('ppn_amount', $taxAmount);
                                    })
                                    ->collapsible(),
                            ]),

                        // Tax and Total Section
                        Section::make()
                            ->columns(3)
                            ->schema([
                                TextInput::make('dpp')
                                    ->label('Dasar Pengenaan Pajak')
                                    ->indonesianMoney()
                                    ->readonly(),

                                \Filament\Forms\Components\Hidden::make('tax')
                                    ->default(0),

                                TextInput::make('ppn_rate')
                                    ->label('PPN Rate (%)')
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => 'PPN rate harus berupa angka'
                                    ])
                                    ->suffix('%')
                                    ->default(fn () => \App\Models\TaxSetting::activeRate('PPN'))
                                    ->reactive()
                                    ->disabled(fn ($operation) => $operation === 'edit')
                                    ->dehydrated(fn ($operation) => $operation !== 'edit')
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $subtotal = (float) \App\Helpers\MoneyHelper::parse($get('subtotal') ?? 0);
                                        $otherFees = $get('other_fees') ?? [];
                                        $manualOtherFeeTotal = (float) collect($otherFees)->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::parse($fee['amount'] ?? 0));

                                        // Include receipt biaya in total calculation
                                        $receiptBiayaItems = $get('receiptBiayaItems') ?? [];
                                        $receiptBiayaTotal = (float) collect($receiptBiayaItems)->sum(fn ($row) => (float) \App\Helpers\MoneyHelper::parse($row['total'] ?? 0));
                                        $totalOtherFee = $manualOtherFeeTotal + $receiptBiayaTotal;
                                        $taxAmount = $subtotal * ((float) $state / 100);

                                        $finalTotal = $subtotal + $totalOtherFee + $taxAmount;
                                        $set('total', $finalTotal);
                                        $set('other_fee', $totalOtherFee);
                                        $set('tax', $taxAmount);

                                        // Update PPN amount display
                                        $set('ppn_amount', $taxAmount);
                                    }),

                                TextInput::make('ppn_amount')
                                    ->label('Nilai PPN (Rp)')
                                    ->indonesianMoney()
                                    ->readonly()
                                    ->placeholder('0'),
                            ]),

                        // Grand Total
                        Section::make('Grand Total Invoice')
                            ->schema([
                                TextInput::make('total')
                                    ->label('')
                                    ->indonesianMoney()
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
                            ->collapsed()
                            ->collapsible()
                            ->schema([
                                Select::make('accounts_payable_coa_id')
                                    ->label('COA Hutang Supplier (Accounts Payable)')
                                    ->options(\App\Models\ChartOfAccount::where('type', 'liability')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => $coa->formatted_name];
                                    }))
                                    ->searchable(['code', 'perusahaan'])
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
                                    ->searchable(['code', 'perusahaan'])
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '1170.06')->first()?->id;
                                    }),

                                Select::make('inventory_coa_id')
                                    ->label('COA Inventory')
                                    ->options(\App\Models\ChartOfAccount::where('type', 'asset')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => $coa->formatted_name];
                                    }))
                                    ->searchable(['code', 'perusahaan'])
                                    ->preload()
                                    ->default(function () {
                                        return \App\Models\ChartOfAccount::where('code', '1140.01')->first()?->id;
                                    }),

                                Select::make('expense_coa_id')
                                    ->label('COA Biaya Lain (Opsional)')
                                    ->options(\App\Models\ChartOfAccount::where('type', 'expense')->get()->mapWithKeys(function ($coa) {
                                        return [$coa->id => $coa->formatted_name];
                                    }))
                                    ->searchable(['code', 'perusahaan'])
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
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Total tidak boleh kosong',
                                    ]),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->collapsed()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                // Recalculate total when receiptBiayaItems changes
                                $subtotal = (float) \App\Helpers\MoneyHelper::parse($get('subtotal') ?? 0);
                                $receiptBiayaItems = $get('receiptBiayaItems') ?? [];
                                $receiptBiayaTotal = (float) collect($receiptBiayaItems)->sum(fn ($row) => (float) \App\Helpers\MoneyHelper::parse($row['total'] ?? 0));
                                $existingOtherFees = $get('other_fees') ?? [];
                                $manualOtherFeeTotal = (float) collect($existingOtherFees)->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::parse($fee['amount'] ?? 0));
                                $totalOtherFee = $receiptBiayaTotal + $manualOtherFeeTotal;
                                
                                $taxAmount = (float) \App\Helpers\MoneyHelper::parse($get('tax') ?? 0);
                                $finalTotal = $subtotal + $totalOtherFee + $taxAmount;
                                $set('total', $finalTotal);

                                // Update PPN amount display
                                $set('ppn_amount', $taxAmount);
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
                            ->rupiah(),
                        Infolists\Components\TextEntry::make('ppn_rate')
                            ->label('PPN Rate (%)')
                            ->state(function (Invoice $record) {
                                return $record->ppn_rate ? $record->ppn_rate . '%' : '0%';
                            }),
                        Infolists\Components\TextEntry::make('dpp')
                            ->label('DPP')
                            ->rupiah(),
                        Infolists\Components\TextEntry::make('other_fee_total')
                            ->label('Other Fees')
                            ->rupiah()
                            ->state(function (Invoice $record) {
                                return $record->getOtherFeeTotalAttribute();
                            }),
                        Infolists\Components\TextEntry::make('total')
                            ->label('Invoice Total')
                            ->rupiah(),
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

                    

                Infolists\Components\Section::make('Journal Entries')
                    ->schema([
                        Infolists\Components\ViewEntry::make('journal_entries_table')
                            ->label('')
                            ->view('filament.infolists.journal-entries-table')
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public static function modifyInfolistQueryUsing(Builder $query): Builder
    {
        return $query->with([
            'fromModel.purchaseOrderItem.purchaseReceiptItem',
            'fromModel.supplier',
            'fromModel.purchaseOrderBiaya',
            'invoiceItem.product',
            'cabang',
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
                    ->rupiah()
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
                    \Filament\Tables\Actions\Action::make('view_journal_entries')
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
                                // Jika multiple entries, gunakan filter dengan format yang sesuai dengan filter options
                                $sourceType = 'App\\Models\\Invoice'; // Format yang sama dengan filter options
                                $sourceId = $record->id;
                                return redirect()->to("/admin/journal-entries?tableFilters[source_type][value]={$sourceType}&tableFilters[source_id][value]={$sourceId}");
                            }
                        }),
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
            ->defaultSort('invoice_date', 'desc')
            ->description(new \Illuminate\Support\HtmlString(
                '<details class="mb-4">' .
                    '<summary class="cursor-pointer font-semibold">Panduan Invoice Pembelian</summary>' .
                    '<div class="mt-2 text-sm">' .
                        '<ul class="list-disc pl-5">' .
                            '<li><strong>Apa ini:</strong> Invoice Pembelian adalah faktur dari supplier untuk pembelian barang/jasa, digunakan untuk mencatat hutang dan memproses pembayaran.</li>' .
                            '<li><strong>Status Flow:</strong> Draft → Sent. Invoice dikirim setelah dibuat dan dapat diedit sebelum dikirim.</li>' .
                            '<li><strong>Validasi:</strong> Subtotal, Tax, PPN dihitung otomatis berdasarkan item. Total invoice digunakan untuk Account Payable.</li>' .
                            '<li><strong>Actions:</strong> <em>View</em> (lihat detail), <em>Edit</em> (ubah invoice), <em>Delete</em> (hapus), <em>Mark as Sent</em> (ubah status ke sent).</li>' .
                            '<li><strong>Filters:</strong> Supplier, Status, Date Range, Amount Range, dll.</li>' .
                            '<li><strong>Permissions:</strong> Tergantung pada cabang user, hanya menampilkan invoice dari cabang tersebut jika tidak memiliki akses all.</li>' .
                            '<li><strong>Integration:</strong> Terintegrasi dengan Purchase Order, Purchase Receipt, dan menghasilkan Account Payable.</li>' .
                        '</ul>' .
                    '</div>' .
                '</details>'
            ));
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        // Calculate PPN amount for display
        $subtotal = $data['subtotal'] ?? 0;
        $ppnRate = $data['ppn_rate'] ?? 0;

        $data['tax'] = 0; // Remove legacy tax; only PPN is used
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
                'amount' => (float) \App\Helpers\MoneyHelper::parse($fee['total'] ?? $fee['amount'] ?? 0),
            ];
        })->toArray();
        
        // Remove temporary fields
        unset($data['other_fees'], $data['receiptBiayaItems']);
        
        // Calculate totals if not set - use PPN only (no separate tax)
        if (!isset($data['total']) || $data['total'] == 0) {
            $subtotal = $data['subtotal'] ?? 0;
            $otherFeeTotal = (float) collect($data['other_fee'] ?? [])->sum(fn ($fee) => (float) \App\Helpers\MoneyHelper::parse($fee['amount'] ?? 0));
            $ppnRate = $data['ppn_rate'] ?? 0;
            $data['total'] = $subtotal + $otherFeeTotal + ($subtotal * $ppnRate / 100);
        }
        $data['tax'] = 0; // Always set tax to 0; only ppn_rate is used
        
        return $data;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    protected static function formatJournalEntriesTableHtml(Invoice $record): string
    {
        $journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\Invoice::class)
            ->where('source_id', $record->id)
            ->with('coa')
            ->get();

        if ($journalEntries->isEmpty()) {
            return '<div class="text-gray-500 italic">No journal entries found</div>';
        }

        $html = '<div class="overflow-x-auto">';
        $html .= '<table class="w-full border-collapse border border-gray-300 text-sm">';
        $html .= '<thead>';
        $html .= '<tr class="bg-gray-50">';
        $html .= '<th class="border border-gray-300 px-3 py-2 text-left font-semibold">COA Code</th>';
        $html .= '<th class="border border-gray-300 px-3 py-2 text-left font-semibold">COA Name</th>';
        $html .= '<th class="border border-gray-300 px-3 py-2 text-left font-semibold">Reference</th>';
        $html .= '<th class="border border-gray-300 px-3 py-2 text-right font-semibold">Debit</th>';
        $html .= '<th class="border border-gray-300 px-3 py-2 text-right font-semibold">Credit</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($journalEntries as $entry) {
            $debit = $entry->debit > 0 ? 'Rp ' . number_format($entry->debit, 0, ',', '.') : '-';
            $credit = $entry->credit > 0 ? 'Rp ' . number_format($entry->credit, 0, ',', '.') : '-';

            $html .= '<tr class="hover:bg-gray-50">';
            $html .= '<td class="border border-gray-300 px-3 py-2 font-mono text-xs">' . htmlspecialchars($entry->coa->code) . '</td>';
            $html .= '<td class="border border-gray-300 px-3 py-2">' . htmlspecialchars(substr($entry->coa->name, 0, 40)) . '</td>';
            $html .= '<td class="border border-gray-300 px-3 py-2">' . htmlspecialchars(substr($entry->reference ?? '', 0, 35)) . '</td>';
            $html .= '<td class="border border-gray-300 px-3 py-2 text-right font-mono ' . ($entry->debit > 0 ? 'text-green-600 font-semibold' : 'text-gray-400') . '">' . htmlspecialchars($debit) . '</td>';
            $html .= '<td class="border border-gray-300 px-3 py-2 text-right font-mono ' . ($entry->credit > 0 ? 'text-red-600 font-semibold' : 'text-gray-400') . '">' . htmlspecialchars($credit) . '</td>';
            $html .= '</tr>';

            // Add description below each entry if exists
            if (!empty($entry->description)) {
                $html .= '<tr class="bg-gray-25">';
                $html .= '<td colspan="5" class="border border-gray-300 px-3 py-1 text-xs text-gray-600 italic">';
                $html .= '<strong>Description:</strong> ' . htmlspecialchars($entry->description);
                $html .= '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
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
