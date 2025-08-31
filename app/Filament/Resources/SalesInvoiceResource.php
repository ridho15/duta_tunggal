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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Invoice Penjualan';
    protected static ?string $modelLabel = 'Invoice Penjualan';
    protected static ?string $pluralModelLabel = 'Invoice Penjualan';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 23;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Invoice Penjualan')
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
                            
                        Section::make('Pilih Customer')
                            ->description('Pilih customer terlebih dahulu')
                            ->schema([
                                Select::make('selected_customer')
                                    ->label('Customer')
                                    ->options(Customer::all()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->required()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_sale_order', null);
                                        $set('selected_delivery_orders', []);
                                        $set('invoiceItem', []);
                                        $set('subtotal', 0);
                                        $set('total', 0);
                                    }),
                            ]),
                            
                        Section::make('Pilih Sale Order')
                            ->description('Pilih sale order dari customer terpilih')
                            ->schema([
                                Select::make('selected_sale_order')
                                    ->label('Sale Order')
                                    ->options(function ($get) {
                                        $customerId = $get('selected_customer');
                                        if (!$customerId) return [];
                                        
                                        return SaleOrder::where('customer_id', $customerId)
                                            ->where('status', 'completed')
                                            ->whereHas('deliverySalesOrder.deliveryOrder', function ($query) {
                                                $query->where('status', 'completed');
                                            })
                                            ->get()
                                            ->filter(function ($so) {
                                                // Check if all DOs are invoiced
                                                $allDOIds = $so->deliverySalesOrder()
                                                    ->with('deliveryOrder')
                                                    ->get()
                                                    ->pluck('deliveryOrder.id')
                                                    ->filter()
                                                    ->toArray();
                                                
                                                if (empty($allDOIds)) return false;
                                                
                                                $invoicedDOIds = Invoice::where('from_model_type', 'App\Models\SaleOrder')
                                                    ->whereNotNull('delivery_orders')
                                                    ->get()
                                                    ->pluck('delivery_orders')
                                                    ->flatten()
                                                    ->intersect($allDOIds)
                                                    ->unique()
                                                    ->toArray();
                                                
                                                return count($invoicedDOIds) < count($allDOIds);
                                            })
                                            ->mapWithKeys(function ($so) {
                                                return [$so->id => "{$so->so_number} - {$so->customer->name}"];
                                            });
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('selected_delivery_orders', []);
                                        $set('invoiceItem', []);
                                        $set('subtotal', 0);
                                        $set('total', 0);
                                    }),
                            ]),
                            
                        Section::make('Pilih Delivery Orders')
                            ->description('Pilih satu atau lebih delivery order dari sale order terpilih')
                            ->schema([
                                Select::make('selected_delivery_orders')
                                    ->label('Delivery Orders')
                                    ->options(function ($get) {
                                        $saleOrderId = $get('selected_sale_order');
                                        if (!$saleOrderId) return [];
                                        
                                        $saleOrder = SaleOrder::find($saleOrderId);
                                        if (!$saleOrder) return [];
                                        
                                        // Get all DOs from this SO
                                        $deliveryOrders = $saleOrder->deliverySalesOrder()
                                            ->with('deliveryOrder')
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
                                        
                                        return $deliveryOrders->mapWithKeys(function ($do) use ($invoicedDOIds) {
                                            $isInvoiced = in_array($do->id, $invoicedDOIds);
                                            $label = "{$do->do_number} - {$do->delivery_date}";
                                            if ($isInvoiced) {
                                                $label .= " (Sudah di-invoice)";
                                            }
                                            return [$do->id => $label];
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
                                        
                                        $saleOrderId = $get('selected_sale_order');
                                        $customerId = $get('selected_customer');
                                        
                                        if (!$saleOrderId || !$customerId) return;
                                        
                                        $saleOrder = SaleOrder::with('customer')->find($saleOrderId);
                                        $deliveryOrders = DeliveryOrder::whereIn('id', $state)->get();
                                        
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
                                                $saleOrderItem = $saleOrder->saleOrderItem()
                                                    ->where('product_id', $item->product_id)
                                                    ->first();
                                                
                                                if ($saleOrderItem) {
                                                    $price = $saleOrderItem->unit_price - $saleOrderItem->discount + $saleOrderItem->tax;
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
                                    ->default(11)
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
                        Hidden::make('from_model_type')->default('App\Models\SaleOrder'),
                        Hidden::make('from_model_id'),
                        Hidden::make('customer_name'),
                        Hidden::make('customer_phone'),
                        Hidden::make('dpp'),
                        Hidden::make('status')->default('draft'),
                        Hidden::make('delivery_orders'),
                        
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
        return parent::getEloquentQuery()->where('from_model_type', 'App\Models\SaleOrder');
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
