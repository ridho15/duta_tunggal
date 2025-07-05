<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryOrderResource\Pages;
use App\Filament\Resources\DeliveryOrderResource\Pages\ViewDeliveryOrder;
use App\Http\Controllers\HelperController;
use App\Models\DeliveryOrder;
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

class DeliveryOrderResource extends Resource
{
    protected static ?string $model = DeliveryOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Delivery Order';

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
                        Select::make('sales_order_id')
                            ->label('From Sales')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $listSaleOrder = SaleOrder::whereIn('id', $state)->get();
                                $items = [];
                                foreach ($listSaleOrder as $saleOrder) {
                                    foreach ($saleOrder->saleOrderItem as $saleOrderItem) {
                                        array_push($items, [
                                            'options_from' => 2,
                                            'sale_order_item_id' => $saleOrderItem->id,
                                            'product_id' => $saleOrderItem->product_id,
                                            'quantity' => $saleOrderItem->quantity,
                                        ]);
                                    }
                                }

                                $set('deliveryOrderItem', $items);
                            })
                            ->relationship('salesOrders', 'so_number', function (Builder $query) {
                                $query->whereIn('status', ['approved', 'completed']);
                            })
                            ->multiple()
                            ->nullable(),
                        DateTimePicker::make('delivery_date')
                            ->label('Delivery Date')
                            ->validationMessages([
                                'required' => 'Delivery Date tidak boleh kosong',
                            ])
                            ->required(),
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
                    })->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'request_close' => 'warning',
                            'request_approve' => 'primary',
                            'closed' => 'danger',
                            'approved' => 'primary',
                            'completed' => 'success',
                        };
                    })
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
                            return Auth::user()->hasPermissionTo('response delivery order') && ($record->status == 'request_approve');
                        })
                        ->action(function ($record) {
                            $deliveryOrderService = app(DeliveryOrderService::class);
                            $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'approved');
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan approve Delivery Order");
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
                        ->action(function ($record) {
                            $deliveryOrderService = app(DeliveryOrderService::class);
                            $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'reject');
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan Reject Delivery Order");
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

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Delivery_Order_' . $record->do_number . '.pdf');
                        }),
                    Action::make('completed')
                        ->label('Complete')
                        ->icon('heroicon-o-check-badge')
                        ->requiresConfirmation()
                        ->visible(function () {
                            return Auth::user()->hasRole(['Super Admin', 'Owner']);
                        })
                        ->color('success')
                        ->action(function ($record) {
                            $deliveryOrderService = app(DeliveryOrderService::class);
                            $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'completed');
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order Completed");
                        }),
                ])
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
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
