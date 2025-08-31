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
                Fieldset::make('Form Invoice Pembelian')
                    ->schema([
                        Section::make('Informasi Invoice')
                            ->columns(2)
                            ->schema([
                                TextInput::make('invoice_number')
                                    ->label('Nomor Invoice')
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
                                
                                DatePicker::make('invoice_date')
                                    ->label('Tanggal Invoice')
                                    ->required()
                                    ->default(now()),
                                
                                DatePicker::make('due_date')
                                    ->label('Tanggal Jatuh Tempo')
                                    ->required(),
                            ]),
                            
                        Section::make('Pilih Supplier')
                            ->description('Pilih supplier terlebih dahulu')
                            ->schema([
                                Select::make('selected_supplier')
                                    ->label('Supplier')
                                    ->options(Supplier::all()->pluck('name', 'id'))
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
                            ]),
                            
                        Section::make('Pilih Purchase Order')
                            ->description('Pilih purchase order dari supplier terpilih')
                            ->schema([
                                Select::make('selected_purchase_order')
                                    ->label('Purchase Order')
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
                                                return [$po->id => "{$po->po_number} - {$po->supplier->name}"];
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
                            
                        Section::make('Pilih Purchase Receipts')
                            ->description('Pilih satu atau lebih purchase receipt dari purchase order terpilih')
                            ->schema([
                                Select::make('selected_purchase_receipts')
                                    ->label('Purchase Receipts')
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
                                            $label = "{$receipt->receipt_number} - {$receipt->receipt_date}";
                                            if ($isInvoiced) {
                                                $label .= " (Sudah di-invoice)";
                                            }
                                            return [$receipt->id => $label];
                                        })->toArray();
                                    })
                                    ->multiple()
                                    ->searchable()
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
                                        $finalTotal = $subtotal + ($subtotal * $tax / 100) + $otherFee;
                                        $set('total', $finalTotal);
                                    }),
                            ]),
                            
                        Section::make('Detail Invoice')
                            ->columns(2)
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->prefix('Rp.')
                                    ->numeric()
                                    ->readonly(),
                                    
                                TextInput::make('tax')
                                    ->label('Pajak (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(2)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $subtotal = $get('subtotal') ?? 0;
                                        $otherFee = $get('other_fee') ?? 0;
                                        $finalTotal = $subtotal + ($subtotal * $state / 100) + $otherFee;
                                        $set('total', $finalTotal);
                                    }),
                                    
                                TextInput::make('other_fee')
                                    ->label('Biaya Lain')
                                    ->prefix('Rp.')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $subtotal = $get('subtotal') ?? 0;
                                        $tax = $get('tax') ?? 0;
                                        $finalTotal = $subtotal + ($subtotal * $tax / 100) + $state;
                                        $set('total', $finalTotal);
                                    }),
                                    
                                TextInput::make('total')
                                    ->label('Total')
                                    ->prefix('Rp.')
                                    ->numeric()
                                    ->readonly(),
                            ]),
                            
                        // Hidden fields
                        Hidden::make('from_model_type')->default('App\Models\PurchaseOrder'),
                        Hidden::make('from_model_id'),
                        Hidden::make('supplier_name'),
                        Hidden::make('supplier_phone'),
                        Hidden::make('dpp'),
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
