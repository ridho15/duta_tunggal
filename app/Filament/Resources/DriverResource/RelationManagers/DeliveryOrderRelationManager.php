<?php

namespace App\Filament\Resources\DriverResource\RelationManagers;

use App\Models\Product;
use App\Models\PurchaseReceiptItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DeliveryOrderRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveryOrder';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('From Delivery Order')
                    ->schema([
                        Fieldset::make('Form Delivery Order')
                            ->schema([
                                TextInput::make('do_number')
                                    ->label('Delivery Order Number')
                                    ->maxLength(255)
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                Select::make('sales_order_id')
                                    ->label('From Sales')
                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $listSaleOrder = SaleOrder::whereIn('id', is_array($state) ? $state : [])->get();
                                        $items = [];
                                        foreach ($listSaleOrder as $saleOrder) {
                                            foreach ($saleOrder->saleOrderItem as $saleOrderItem) {
                                                $available = $saleOrderItem->availableQuantityForDelivery();
                                                if ($available <= 0) {
                                                    continue;
                                                }
                                                array_push($items, [
                                                    'options_from' => 2,
                                                    'sale_order_item_id' => $saleOrderItem->id,
                                                    'product_id' => $saleOrderItem->product_id,
                                                    'quantity' => $available,
                                                ]);
                                            }
                                        }

                                        $set('deliveryOrderItem', $items);
                                    })
                                    ->relationship('salesOrders', 'so_number', function (Builder $query, $record) {
                                        // Hanya SO layak kirim yang masih punya sisa yang belum terikat DO manapun;
                                        // SO yang sudah terhubung ke DO yang sedang diedit tetap tampil.
                                        $linked = ($record instanceof \App\Models\DeliveryOrder && $record->exists)
                                            ? $record->salesOrders()->withoutGlobalScopes()->pluck('sale_orders.id')->map(fn ($id) => (int) $id)->all()
                                            : [];
                                        $query->deliverable($linked);
                                    })
                                    ->rule(function ($record) {
                                        return function (string $attribute, $value, \Closure $fail) use ($record) {
                                            $linked = ($record instanceof \App\Models\DeliveryOrder && $record->exists)
                                                ? $record->salesOrders()->withoutGlobalScopes()->pluck('sale_orders.id')->map(fn ($id) => (int) $id)->all()
                                                : [];
                                            $errors = app(\App\Services\DeliveryOrderSourceValidator::class)->errors(
                                                is_array($value) ? $value : [],
                                                ($record instanceof \App\Models\DeliveryOrder && $record->exists) ? (int) $record->id : null,
                                                $linked
                                            );
                                            foreach ($errors as $message) {
                                                $fail($message);
                                            }
                                        };
                                    })
                                    ->multiple()
                                    ->nullable(),
                                DateTimePicker::make('delivery_date')
                                    ->required(),
                                Select::make('vehicle_id')
                                    ->label('Vehicle')
                                    ->preload()
                                    ->searchable()
                                    ->relationship('vehicle', 'plate')
                                    ->required(),
                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->nullable(),
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
                                                $listSalesOrderId = $get('../../sales_order_id');
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
                                                $set('product_id', $saleOrderItem->product_id);
                                                $set('quantity', $saleOrderItem->quantity);
                                            })
                                            ->searchable()
                                            ->relationship('saleOrderItem', 'id', function (Builder $query, $get) {
                                                $listSalesOrderId = $get('../../sales_order_id');
                                                $query->when(count($listSalesOrderId) > 0, function (Builder $query) use ($listSalesOrderId) {
                                                    $query->whereIn('sale_order_id', $listSalesOrderId);
                                                });
                                            })
                                            ->getOptionLabelFromRecordUsing(function (SaleOrderItem $saleOrderItem) {
                                                return "{$saleOrderItem->saleOrder->so_number} - ({$saleOrderItem->product->sku}) {$saleOrderItem->product->name}";
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
                                            ->default(0),
                                        Textarea::make('reason')
                                            ->label('Reason')
                                            ->nullable()
                                    ])
                            ])
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('do_number')
                    ->label('Delivery Order Number')
                    ->searchable(),
                TextColumn::make('delivery_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('vehicle')
                    ->label('Vehicle')
                    ->formatStateUsing(function ($state) {
                        return $state->plate . ' - ' . $state->type;
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => \App\Models\DeliveryOrder::statusLabel($state))
                    ->color(fn ($state) => \App\Models\DeliveryOrder::statusColor($state))
                    ->badge(),
                TextColumn::make('salesOrders.so_number')
                    ->label('Sales Orders')
                    ->badge()
                    ->searchable(),
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
                //
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
