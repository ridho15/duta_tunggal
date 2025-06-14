<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleOrderResource\Pages;
use App\Filament\Resources\SaleOrderResource\Pages\ViewSaleOrder;
use App\Filament\Resources\SaleOrderResource\RelationManagers\SaleOrderItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Services\SalesOrderService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SaleOrderResource extends Resource
{
    protected static ?string $model = SaleOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Sales Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Sales')
                    ->schema([
                        Placeholder::make('status')
                            ->label('Status')
                            ->content(function ($record) {
                                return $record ? Str::upper($record->status) : '-';
                            }),
                        Select::make('options_form')
                            ->label('Opions From')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->loadingMessage("loading...")
                            ->options(function () {
                                return [
                                    '0' => 'None',
                                    '1' => 'Refer Penjualan',
                                    '2' => 'Refer Quotation',
                                ];
                            })->default(0),
                        Select::make('quotation_id')
                            ->label('Quotation')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($set, $get, $state) {
                                $items = [];
                                $quotation = Quotation::find($state);
                                foreach ($quotation->quotationItem as $item) {
                                    array_push($items, [
                                        'product_id' => $item->product_id,
                                        'quantity' => $item->quantity,
                                        'unit_price' => $item->unit_price,
                                        'discount' => $item->discount,
                                        'tax' => $item->tax,
                                        'notes' => $item->notes
                                    ]);
                                }
                                $set('total_amount', $quotation->total_amount);
                                $set('customer_id', $quotation->customer_id);
                                $set('saleOrderItem', $items);
                            })
                            ->visible(function ($get) {
                                return $get('options_form') == 2;
                            })
                            ->options(Quotation::where('status', 'approve')->select(['id', 'customer_id', 'quotation_number'])->get()->pluck('quotation_number', 'id'))
                            ->required(),
                        Select::make('sale_order_id')
                            ->label('Sales Order')
                            ->preload()
                            ->loadingMessage('Loading ...')
                            ->reactive()
                            ->searchable()
                            ->visible(function ($get) {
                                return $get('options_form') == 1;
                            })
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $items = [];
                                $saleOrder = SaleOrder::find($state);
                                foreach ($saleOrder->saleOrderItem as $item) {
                                    array_push($items, [
                                        'product_id' => $item->product_id,
                                        'unit_price' => $item->unit_price,
                                        'quantity' => $item->quantity,
                                        'discount' => $item->discount,
                                        'tax' => $item->tax,
                                        'notes' => $item->notes,
                                    ]);
                                }
                                $set('total_amount', $saleOrder->total_amount);
                                $set('customer_id', $saleOrder->customer_id);
                                $set('saleOrderItem', $items);
                            })
                            ->options(SaleOrder::select(['id', 'so_number', 'customer_id'])->get()->pluck('so_number', 'id'))
                            ->required(),
                        Select::make('customer_id')
                            ->required()
                            ->label('Customer')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->relationship('customer', 'name')
                            ->createOptionForm([
                                Fieldset::make('Form Customer')
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('address')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('phone')
                                            ->tel()
                                            ->maxLength(15)
                                            ->rules(['regex:/^08[0-9]{8,12}$/'])
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('email')
                                            ->email()
                                            ->required()
                                            ->maxLength(255)
                                    ]),
                            ]),
                        TextInput::make('so_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        DatePicker::make('order_date')
                            ->required(),
                        DatePicker::make('delivery_date'),
                        TextInput::make('shipped_to')
                            ->label('Shipped To')
                            ->nullable(),
                        TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->prefix('Rp.')
                            ->required()
                            ->disabled()
                            ->reactive()
                            ->default(0)
                            ->numeric(),
                        Repeater::make('saleOrderItem')
                            ->relationship()
                            ->columnSpanFull()
                            ->reactive()
                            ->columns(3)
                            ->addActionLabel("Add Items")
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $product = Product::find($state);
                                        $set('unit_price', $product->sell_price);
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax')));
                                    })
                                    ->required()
                                    ->relationship('product', 'id')
                                    ->getOptionLabelFromRecordUsing(function (Product $product) {
                                        return "({$product->sku}) {$product->name}";
                                    }),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $state, $get('tax')));
                                    })
                                    ->required()
                                    ->default(0),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $state));
                                    })
                                    ->prefix('Rp.'),
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax')));
                                    })
                                    ->prefix('Rp.'),
                                TextInput::make('tax')
                                    ->label('Tax')
                                    ->numeric()
                                    ->reactive()
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $set('subtotal',  HelperController::hitungSubtotal($get('quantity'), $get('unit_price'), $get('discount'), $get('tax')));
                                    })
                                    ->default(0)
                                    ->prefix('Rp.'),
                                TextInput::make('subtotal')
                                    ->label('Sub Total')
                                    ->reactive()
                                    ->readOnly()
                                    ->default(0)
                                    ->afterStateHydrated(function ($component, $record) {
                                        if ($record) {
                                            $component->state(HelperController::hitungSubtotal($record->quantity, $record->unit_price, $record->discount, $record->tax));
                                        }
                                    })
                                    ->afterStateUpdated(function ($component, $state, $livewire) {
                                        $quantity = $livewire->data['quantity'] ?? 0;
                                        $unit_price = $livewire->data['unit_price'] ?? 0;
                                        $discount = $livewire->data['discount'] ?? 0;
                                        $tax = $livewire->data['tax'] ?? 0;
                                        $component->state(HelperController::hitungSubtotal($$quantity, $unit_price, $discount, $tax));
                                    })
                                    ->prefix('Rp.')
                            ])
                    ])
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('so_number')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'draft' => 'gray',
                            'process' => 'warning',
                            'completed' => 'success',
                            'canceled' => 'danger'
                        };
                    })
                    ->badge(),
                TextColumn::make('shipped_to')
                    ->label('Shipped To')
                    ->searchable(),
                TextColumn::make('delivery_date')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->numeric()
                    ->money('idr')
                    ->sortable(),
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
                        ->color('primary'),
                    DeleteAction::make(),
                    Action::make('request_approve')
                        ->label('Request Approve')
                        ->requiresConfirmation()
                        ->color('success')
                        ->icon('heroicon-o-arrow-uturn-up')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request sales order') && $record->status == 'draft';
                        })
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->requestApprove($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan request approve");
                        }),
                    Action::make('request_close')
                        ->label('Request Close')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request sales order') && ($record->status != 'approved' || $record->status != 'confirmed' || $record->status != 'close' || $record->status != 'canceled' || $record->status == 'draft');
                        })
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->requestClose($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan request close");
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->requiresConfirmation()
                        ->color('success')
                        ->icon('heroicon-o-check-badge')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response sales order') && ($record->status == 'request_approve');
                        })
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->requestClose($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan request close");
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->requiresConfirmation()
                        ->color('success')
                        ->icon('heroicon-o-check-badge')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response sales order') && ($record->status == 'request_approve');
                        })
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->requestClose($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan request close");
                        }),
                    Action::make('sync_total_amount')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->label('Sync Total Amount')
                        ->color('primary')
                        ->action(function ($record) {
                            $salesOrderService = app(SalesOrderService::class);
                            $salesOrderService->updateTotalAmount($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total berhasil di update");
                        })
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
            SaleOrderItemRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaleOrders::route('/'),
            'create' => Pages\CreateSaleOrder::route('/create'),
            'view' => ViewSaleOrder::route('/{record}'),
            'edit' => Pages\EditSaleOrder::route('/{record}/edit'),
        ];
    }
}
