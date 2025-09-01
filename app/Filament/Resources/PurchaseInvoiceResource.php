<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseInvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Invoice Pembelian';
    protected static ?string $modelLabel = 'Invoice Pembelian';
    protected static ?string $pluralModelLabel = 'Invoice Pembelian';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 24;

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
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_purchase_order', null);
                                        $set('selected_purchase_receipts', []);
                                        $set('invoiceItem', []);
                                        $set('subtotal', 0);
                                        $set('total', 0);
                                    }),
                                    
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
                                        $set('subtotal', 0);
                                        $set('total', 0);
                                    }),
                            ]),

                        // Invoice Info Section
                        Section::make()
                            ->columns(2)
                            ->schema([
                                TextInput::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->required()
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
                                    ->default(now()),
                                    
                                DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->required()
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
                                            $label = "{$receipt->receipt_number} - Rp. " . number_format($receipt->total ?? 0, 0, ',', '.');
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
                                            $set('subtotal', 0);
                                            $set('total', 0);
                                            return;
                                        }
                                        
                                        $purchaseOrderId = $get('selected_purchase_order');
                                        $supplierId = $get('selected_supplier');
                                        
                                        if (!$purchaseOrderId || !$supplierId) return;
                                        
                                        $purchaseOrder = PurchaseOrder::with('supplier')->find($purchaseOrderId);
                                        $purchaseReceipts = PurchaseReceipt::whereIn('id', $state)->get();
                                        
                                        // Set supplier info
                                        $set('supplier_name', $purchaseOrder->supplier->name);
                                        $set('supplier_phone', $purchaseOrder->supplier->phone ?? '');
                                        $set('from_model_type', 'App\Models\PurchaseOrder');
                                        $set('from_model_id', $purchaseOrderId);
                                        
                                        // Calculate items from purchase receipts
                                        $items = [];
                                        $subtotal = 0;
                                        
                                        foreach ($purchaseReceipts as $receipt) {
                                            foreach ($receipt->purchaseReceiptItem as $item) {
                                                $purchaseOrderItem = $purchaseOrder->purchaseOrderItem()
                                                    ->where('product_id', $item->product_id)
                                                    ->first();
                                                
                                                if ($purchaseOrderItem) {
                                                    $price = $purchaseOrderItem->unit_price + $purchaseOrderItem->tax - $purchaseOrderItem->discount;
                                                    $total = $price * $item->quantity_received;
                                                    
                                                    $items[] = [
                                                        'product_id' => $item->product_id,
                                                        'quantity' => $item->quantity_received,
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
                                        $set('purchase_receipts', $state);
                                        
                                        // Calculate tax and total
                                        $tax = $get('tax') ?? 0;
                                        $otherFee = $get('other_fee') ?? 0;
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $otherFee + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                    }),
                            ]),

                        // Biaya Lain Section
                        Section::make('Biaya Lain - lain')
                            ->columns(2)
                            ->schema([
                                TextInput::make('other_cost_name')
                                    ->label('Biaya Lain - lain')
                                    ->default('Biaya Lain - lain')
                                    ->reactive(),
                                    
                                TextInput::make('other_cost_total')
                                    ->label('Total Biaya')
                                    ->prefix('Rp.')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $subtotal = $get('subtotal') ?? 0;
                                        $tax = $get('tax') ?? 0;
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $state + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                        $set('other_fee', $state);
                                    }),
                                    
                                Forms\Components\Toggle::make('add_other_cost')
                                    ->label('Tambah Biaya')
                                    ->reactive()
                                    ->columnSpanFull(),
                            ]),

                        // Tax and Total Section
                        Section::make()
                            ->columns(4)
                            ->schema([
                                TextInput::make('dpp')
                                    ->label('DPP')
                                    ->prefix('Rp.')
                                    ->numeric()
                                    ->readonly(),
                                    
                                TextInput::make('other_fee')
                                    ->label('Other Fee')
                                    ->prefix('Rp.')
                                    ->numeric()
                                    ->readonly(),
                                    
                                TextInput::make('tax')
                                    ->label('Tax (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $subtotal = $get('subtotal') ?? 0;
                                        $otherFee = $get('other_fee') ?? 0;
                                        $ppnRate = $get('ppn_rate') ?? 0;
                                        $finalTotal = $subtotal + $otherFee + ($subtotal * $state / 100) + ($subtotal * $ppnRate / 100);
                                        $set('total', $finalTotal);
                                    }),
                                    
                                TextInput::make('ppn_rate')
                                    ->label('PPN Rate (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $subtotal = $get('subtotal') ?? 0;
                                        $otherFee = $get('other_fee') ?? 0;
                                        $tax = $get('tax') ?? 0;
                                        $finalTotal = $subtotal + $otherFee + ($subtotal * $tax / 100) + ($subtotal * $state / 100);
                                        $set('total', $finalTotal);
                                    }),
                            ]),

                        // Grand Total
                        Section::make('Grand Total Invoice')
                            ->schema([
                                TextInput::make('total')
                                    ->label('')
                                    ->prefix('Rp.')
                                    ->numeric()
                                    ->readonly()
                                    ->extraAttributes(['class' => 'text-lg font-bold']),
                            ]),
                            
                        // Hidden fields
                        Hidden::make('from_model_type')->default('App\Models\PurchaseOrder'),
                        Hidden::make('from_model_id'),
                        Hidden::make('supplier_name'),
                        Hidden::make('supplier_phone'),
                        Hidden::make('subtotal'),
                        Hidden::make('status')->default('draft'),
                        Hidden::make('purchase_receipts'),
                        
                        Repeater::make('invoiceItem')
                            ->label('Item Invoice')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('price')
                                    ->label('Price')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->required(),
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
                return $query->where('from_model_type', 'App\Models\PurchaseOrder');
            })
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->sortable(),
                    
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
        return parent::getEloquentQuery()->where('from_model_type', 'App\Models\PurchaseOrder');
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
