<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers\PurchaseOrderItemRelationManager;
use App\Http\Controllers\HelperController;
use App\Models\Invoice;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
use App\Services\InvoiceService;
use App\Services\PurchaseOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Filament\Tables\Enums\ActionsPosition;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Purchase Order';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Form Purchase Order')
                    ->schema([
                        Section::make('Reference')
                            ->description("Referensi untuk membuat PO, boleh di abaikan")
                            ->columns(2)
                            ->schema([
                                Radio::make('refer_model_type')
                                    ->label('Refer From')
                                    ->reactive()
                                    ->inlineLabel()
                                    ->options([
                                        'App\Models\SaleOrder' => 'Sales Order',
                                        'App\Models\OrderRequest' => 'Order Request'
                                    ])
                                    ->nullable(),
                                Select::make('refer_model_id')
                                    ->label('Refer From')
                                    ->reactive()
                                    ->preload()
                                    ->searchable()
                                    ->options(function ($set, $get, $state) {
                                        if ($get('refer_model_type') == 'App\Models\SaleOrder') {
                                            return SaleOrder::select(['id', 'so_number'])->get()->pluck('so_number', 'id');
                                        } elseif ($get('refer_model_type') == 'App\Models\OrderRequest') {
                                            return OrderRequest::where('status', 'approved')->select(['id', 'request_number'])->get()->pluck('request_number', 'id');
                                        }
                                        return [];
                                    })
                                    ->afterStateUpdated(function ($set, $get, $state) {
                                        $items = [];
                                        if ($get('refer_model_type') == 'App\Models\SaleOrder') {
                                            $saleOrder = SaleOrder::find($state);
                                            foreach ($saleOrder->saleOrderItem as $saleOrderItem) {
                                                $subtotal = ((int)$saleOrderItem->quantity * (int) $saleOrderItem->unit_price) - (int) $saleOrderItem->discount + (int) $saleOrderItem->tax;
                                                array_push($items, [
                                                    'product_id' => $saleOrderItem->product_id,
                                                    'quantity' => $saleOrderItem->quantity,
                                                    'unit_price' => $saleOrderItem->product->cost_price,
                                                    'discount' => 0,
                                                    'tax' => 0,
                                                    'subtotal' => $subtotal
                                                ]);
                                            }
                                        } elseif ($get('refer_model_type') == 'App\Models\OrderRequest') {
                                            $orderRequest = OrderRequest::find($state);
                                            foreach ($orderRequest->orderRequestItem as $orderRequestItem) {
                                                $subtotal = ((int)$orderRequestItem->quantity * (int) $orderRequestItem->unit_price) - (int) $orderRequestItem->discount + (int) $orderRequestItem->tax;
                                                array_push($items, [
                                                    'product_id' => $orderRequestItem->product_id,
                                                    'quantity' => $orderRequestItem->quantity,
                                                    'unit_price' => $orderRequestItem->product->cost_price,
                                                    'discount' => 0,
                                                    'tax' => 0,
                                                    'subtotal' => $subtotal
                                                ]);
                                            }
                                        }

                                        $set('purchaseOrderItem', $items);
                                    })
                                    ->nullable(),
                            ]),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->preload()
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->required(),
                        TextInput::make('po_number')
                            ->required()
                            ->reactive()
                            ->suffixAction(
                                ActionsAction::make('generatePoNumber')
                                    ->icon('heroicon-m-arrow-path') // ikon reload
                                    ->tooltip('Generate PO Number')
                                    ->action(function ($set, $get, $state) {
                                        $purchaseOrderService = app(PurchaseOrderService::class);
                                        $set('po_number', $purchaseOrderService->generatePoNumber());
                                    })
                            )
                            ->maxLength(255),
                        DatePicker::make('order_date')
                            ->required(),
                        DatePicker::make('expected_date'),
                        TextInput::make('total_amount')
                            ->required()
                            ->prefix('Rp.')
                            ->numeric()
                            ->disabled()
                            ->default(0),
                        Textarea::make('note')
                            ->label('Notes'),
                        Toggle::make('is_asset')
                            ->label('Asset ?')
                            ->required(),
                        Repeater::make('purchaseOrderItem')
                            ->label('Order Item')
                            ->columnSpanFull()
                            ->relationship()
                            ->columns(3)
                            ->defaultItems(0)
                            ->afterStateUpdated(function (Get $get, $state, $livewire) {
                                $purchaseOrder = $livewire->getRecord();
                                $total_amount = 0;
                                foreach ($state as $item) {
                                    $total_amount += ($item['quantity'] * $item['unit_price']) - $item['discount'] + $item['tax'];
                                }

                                if ($purchaseOrder) {
                                    $purchaseOrder->update([
                                        'total_amount' => $total_amount
                                    ]);
                                }
                            })
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        return "{$record->sku} - {$record->name}";
                                    })
                                    ->relationship('product', 'name')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        $product = Product::find($state);
                                        $set('unit_price', $product->cost_price);

                                        $subtotal = ($get('quantity') * $get('unit_price')) - $get('discount') + $get('tax');
                                        $set('subtotal', $subtotal);
                                    })
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $subtotal = ($get('quantity') * $get('unit_price')) - $get('discount') + $get('tax');
                                        $set('subtotal', $subtotal);
                                    })
                                    ->numeric(),
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $subtotal = ($get('quantity') * $get('unit_price')) - $get('discount') + $get('tax');
                                        $set('subtotal', $subtotal);
                                    })
                                    ->prefix('Rp.')
                                    ->default(0),
                                TextInput::make('discount')
                                    ->label('Discount')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $subtotal = ($get('quantity') * $get('unit_price')) - $get('discount') + $get('tax');
                                        $set('subtotal', $subtotal);
                                    })
                                    ->prefix('Rp.')
                                    ->default(0),
                                TextInput::make('tax')
                                    ->label('Tax')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $subtotal = ($get('quantity') * $get('unit_price')) - $get('discount') + $get('tax');
                                        $set('subtotal', $subtotal);
                                    })
                                    ->prefix('Rp.')
                                    ->default(0),
                                TextInput::make('subtotal')
                                    ->label('Sub Total')
                                    ->reactive()
                                    ->prefix('Rp.')
                                    ->readOnly(),
                                Radio::make('opsi_harga')
                                    ->label('Opsi Harga')
                                    ->inline()
                                    ->required()
                                    ->columnSpanFull()
                                    ->options([
                                        'default' => 'Default',
                                        'negotiated' => 'Negotiated',
                                        'promo' => 'Promo'
                                    ])
                                    ->default('default')
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->orderByDesc('order_date');
            })
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable(),
                TextColumn::make('po_number')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status PO')
                    ->formatStateUsing(function ($state) {
                        return Str::upper($state);
                    })
                    ->color(function ($state) {
                        switch ($state) {
                            case 'draft':
                                return 'gray';
                                break;
                            case 'partially_received':
                                return 'warning';
                                break;
                            case 'request_close':
                                return 'warning';
                                break;
                            case 'request_approval':
                                return 'info';
                                break;
                            case 'closed':
                                return 'danger';
                                break;
                            case 'completed':
                                return 'success';
                                break;
                        }
                    })
                    ->badge(),
                TextColumn::make('expected_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('idr')
                    ->sortable(),
                TextColumn::make('purchaseOrderItem.product.name')
                    ->label('Product')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->badge(),
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
                IconColumn::make('is_asset')
                    ->label('Asset?')
                    ->boolean(),
                TextColumn::make('date_approved')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('close_requested_by')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('close_requested_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('closed_by')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('closed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('completed_by')
                    ->numeric()
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
                        ->hidden(function ($record) {
                            return $record->status == 'completed';
                        })
                        ->color('success'),
                    DeleteAction::make()
                        ->hidden(function ($record) {
                            return $record->status == 'completed';
                        }),
                    Action::make('konfirmasi')
                        ->label('Konfirmasi')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response purchase order') && ($record->status == 'request_approval' || $record->status == 'request_close');
                        })
                        ->requiresConfirmation()
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->form(function ($record) {
                            if ($record->status == 'request_close') {
                                return [
                                    Textarea::make('close_reason')
                                        ->label('Close Reason')
                                        ->required()
                                        ->string()
                                ];
                            }

                            return null;
                        })
                        ->action(function (array $data, $record) {
                            if ($record->status == 'request_approval') {
                                $record->update([
                                    'status' => 'approved',
                                    'date_approved' => Carbon::now(),
                                    'approved_by' => Auth::user()->id,
                                ]);
                            } elseif ($record->status == 'request_close') {
                                $record->update([
                                    'close_reason' => $data['close_reason'],
                                    'status' => 'closed',
                                    'closed_at' => Carbon::now(),
                                    'closed_by' => Auth::user()->id,
                                ]);
                            }
                        }),
                    Action::make('tolak')
                        ->label('Tolak')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('response purchase order') && ($record->status == 'request_approval' || $record->status == 'request_close');
                        })
                        ->requiresConfirmation()
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($record) {
                            $record->update([
                                'status' => 'draft'
                            ]);
                        }),
                    Action::make('request_approval')
                        ->label('Request Approval')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request purchase order') && $record->status == 'draft';
                        })
                        ->requiresConfirmation()
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('success')
                        ->action(function ($record) {
                            $record->update([
                                'status' => 'request_approval'
                            ]);
                        }),
                    Action::make('request_close')
                        ->label('Request Close')
                        ->visible(function ($record) {
                            return Auth::user()->hasPermissionTo('request purchase order') && ($record->status != 'closed' || $record->status != 'completed');
                        })
                        ->hidden(function ($record) {
                            return $record->status == 'completed';
                        })
                        ->requiresConfirmation()
                        ->form([
                            Textarea::make('close_reason')
                                ->label('Close Reason')
                                ->string()
                                ->required(),
                        ])
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (array $data, $record) {
                            $record->update([
                                'status' => 'request_close',
                                'close_reason' => $data['close_reason']
                            ]);
                        }),
                    Action::make('cetak_pdf')
                        ->label('Cetak PDF')
                        ->icon('heroicon-o-document-check')
                        ->color('danger')
                        ->visible(function ($record) {
                            return $record->status != 'draft' && $record->status != 'closed';
                        })
                        ->action(function ($record) {
                            $pdf = Pdf::loadView('pdf.purchase-order', [
                                'purchaseOrder' => $record
                            ])->setPaper('A4', 'potrait');

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->stream();
                            }, 'Purchase_Order_' . $record->po_number . '.pdf');
                        }),
                    Action::make('update_total_amount')
                        ->label('Sync Total Amount')
                        ->color('primary')
                        ->hidden(function ($record) {
                            return $record->status == 'completed';
                        })
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->action(function ($record) {
                            $purchaseOrderService = app(PurchaseOrderService::class);
                            $purchaseOrderService->updateTotalAmount($record);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total amount berhasil disinkronkan");
                        }),
                    Action::make('terbit_invoice')
                        ->label('Terbitkan Invoice')
                        ->visible(function ($record) {
                            return $record->status == 'completed';
                        })
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->requiresConfirmation()
                        ->form([
                            TextInput::make('invoice_number')
                                ->label('Invoice Number')
                                ->required()
                                ->reactive()
                                ->suffixAction(ActionsAction::make('generateInvoiceNumber')
                                    ->icon('heroicon-m-arrow-path') // ikon reload
                                    ->tooltip('Generate Invoice Number')
                                    ->action(function ($set, $get, $state) {
                                        $invoiceService = app(InvoiceService::class);
                                        $set('invoice_number', $invoiceService->generateInvoiceNumber());
                                    }))
                                ->maxLength(255),
                            DatePicker::make('invoice_date')
                                ->required(),
                            TextInput::make('tax')
                                ->required()
                                ->prefix('Rp.')
                                ->numeric()
                                ->default(0),
                            TextInput::make('other_fee')
                                ->required()
                                ->numeric()
                                ->prefix('Rp.')
                                ->default(0),
                        ])
                        ->action(function (array $data, $record) {
                            // Check invoice
                            $invoice = Invoice::where('invoice_number', $data['invoice_number'])->first();
                            if ($invoice) {
                                HelperController::sendNotification(isSuccess: false, title: "Information", message: "Invoice number sudah digunakan");
                                return;
                            }
                            $purchaseOrderService = app(PurchaseOrderService::class);
                            $purchaseOrderService->generateInvoice($record, $data);
                            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Generate invoice berhasil");
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
            PurchaseOrderItemRelationManager::class
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
