<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Http\Controllers\HelperController;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\Warehouse;
use App\Services\QuotationService;
use App\Services\SalesOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    protected function getActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make()
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary'),
                DeleteAction::make()
                    ->icon('heroicon-o-trash'),
                Action::make('download_file')
                    ->label('Download File')
                    ->color('success')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->openUrlInNewTab()
                    ->hidden(function ($record) {
                        return !$record->po_file_path;
                    })
                    ->url(function ($record) {
                        return asset('storage' . $record->po_file_path);
                    }),
                Action::make('request_approve')
                    ->label('Request Approve')
                    ->icon('heroicon-o-arrow-uturn-up')
                    ->color('success')
                    ->hidden(function ($record) {
                        return !Auth::user()->hasPermissionTo('request-approve quotation') || $record->status != 'draft';
                    })
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $quotationService = app(QuotationService::class);
                        $quotationService->requestApprove($record);
                        HelperController::sendNotification(isSuccess: true, title: "Information", message: "Mengajukan Approve Berhasil");
                    }),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->hidden(function ($record) {
                        return $record->status != 'request_approve' || !Auth::user()->hasPermissionTo('approve quotation');
                    })
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $quotationService = app(QuotationService::class);
                        $quotationService->approve($record);
                        HelperController::sendNotification(isSuccess: true, title: "Success", message: "Berhasil melakukan approve quotation");
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->hidden(function ($record) {
                        return $record->status != 'request_approve' || !Auth::user()->hasPermissionTo('reject quotation');
                    })
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $quotationService = app(QuotationService::class);
                        $quotationService->reject($record);
                        HelperController::sendNotification(isSuccess: true, title: "Danger", message: "Quotation di reject");
                    }),
                Action::make('sync_total_amount')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->label('Sync Total Amount')
                    ->color('primary')
                    ->action(function ($record) {
                        $quotationService = app(QuotationService::class);
                        $quotationService->updateTotalAmount($record);
                        HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total berhasil di update");
                    }),
                Action::make('pdf_quotation')
                    ->label('Download PDF')
                    ->icon('heroicon-o-document')
                    ->color('danger')
                    ->action(function ($record) {
                        $pdf = Pdf::loadView('pdf.quotation', [
                            'quotation' => $record
                        ])->setPaper('A4', 'portrait');

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->stream();
                        }, 'Quotation_' . $record->quotation_number . '.pdf');
                    }),
                Action::make('create_sale_order')
                    ->label('Buat Sales Order')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->visible(function ($record) {
                        $user = Auth::user();
                        $hasPermission = $user && $user->hasPermissionTo('create sales order');
                        $isApproved = $record->status == 'approve';
                        
                        Log::debug('ViewQuotation: create_sale_order visibility check', [
                            'quotation_id' => $record->id,
                            'quotation_number' => $record->quotation_number,
                            'status' => $record->status,
                            'is_approved' => $isApproved,
                            'user_id' => $user ? $user->id : null,
                            'user_name' => $user ? $user->name : null,
                            'has_permission' => $hasPermission,
                            'visible' => $isApproved && $hasPermission
                        ]);
                        
                        return $isApproved && $hasPermission;
                    })
                    ->form([
                        Section::make('Informasi Quotation')
                            ->schema([
                                Placeholder::make('quotation_number')
                                    ->label('Nomor Quotation')
                                    ->content(fn($record) => $record->quotation_number),
                                Placeholder::make('customer_name')
                                    ->label('Customer')
                                    ->content(fn($record) => $record->customer->name ?? '-'),
                                Placeholder::make('total_amount')
                                    ->label('Total Amount')
                                    ->content(fn($record) => 'Rp ' . number_format($record->total_amount, 0, ',', '.')),
                                Placeholder::make('item_count')
                                    ->label('Jumlah Item')
                                    ->content(fn($record) => $record->quotationItem->count() . ' item(s)'),
                            ])->columns(2),
                        Section::make('Sales Order Baru')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('so_number')
                                            ->label('Nomor Sales Order')
                                            ->default(fn() => app(SalesOrderService::class)->generateSoNumber())
                                            ->required()
                                            ->unique(table: 'sale_orders', column: 'so_number')
                                            ->validationMessages([
                                                'required' => 'Nomor Sales Order wajib diisi',
                                                'unique' => 'Nomor Sales Order sudah digunakan'
                                            ])
                                            ->suffixAction(
                                                \Filament\Forms\Components\Actions\Action::make('generateSoNumber')
                                                    ->icon('heroicon-o-arrow-path')
                                                    ->tooltip('Generate Nomor Sales Order Baru')
                                                    ->action(function ($set) {
                                                        $set('so_number', app(SalesOrderService::class)->generateSoNumber());
                                                    })
                                            ),
                                        DatePicker::make('order_date')
                                            ->label('Tanggal Order')
                                            ->default(now())
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Tanggal order wajib dipilih'
                                            ]),
                                        DatePicker::make('delivery_date')
                                            ->label('Tanggal Pengiriman')
                                            ->validationMessages([
                                                'required' => 'Tanggal pengiriman wajib dipilih'
                                            ]),
                                        Select::make('tipe_pengiriman')
                                            ->label('Tipe Pengiriman')
                                            ->options([
                                                'Ambil Sendiri' => 'Ambil Sendiri',
                                                'Kirim Langsung' => 'Kirim Langsung'
                                            ])
                                            ->default('Kirim Langsung')
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Tipe pengiriman wajib dipilih'
                                            ]),
                                    ]),
                                Repeater::make('saleOrderItems')
                                    ->label('Item Sales Order')
                                    ->schema([
                                        Hidden::make('product_id'),
                                        Placeholder::make('product_info')
                                            ->label('Produk')
                                            ->content(function ($get, $record) {
                                                $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                if ($quotationItem) {
                                                    return "({$quotationItem->product->sku}) {$quotationItem->product->name}";
                                                }
                                                return '-';
                                            })
                                            ->columnSpan(2),
                                        TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->default(function ($get, $record) {
                                                $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                return $quotationItem ? $quotationItem->quantity : 0;
                                            })
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Quantity wajib diisi',
                                                'numeric' => 'Quantity harus berupa angka'
                                            ])
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                $quantity = $state ?? 0;
                                                $unitPrice = HelperController::parseIndonesianMoney($get('unit_price') ?? 0);
                                                $discount = $get('discount') ?? 0;
                                                $tax = $get('tax') ?? 0;
                                                $subtotal = HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $tax);
                                                $set('subtotal', $subtotal);
                                            }),
                                        TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->default(function ($get, $record) {
                                                $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                return $quotationItem ? $quotationItem->unit_price : 0;
                                            })
                                            ->required()
                                            ->indonesianMoney()
                                            ->validationMessages([
                                                'required' => 'Unit Price wajib diisi',
                                                'numeric' => 'Unit Price harus berupa angka'
                                            ])
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                $quantity = $get('quantity') ?? 0;
                                                $unitPrice = HelperController::parseIndonesianMoney($state ?? 0);
                                                $discount = $get('discount') ?? 0;
                                                $tax = $get('tax') ?? 0;
                                                $subtotal = HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $tax);
                                                $set('subtotal', $subtotal);
                                            }),
                                        Select::make('warehouse_id')
                                            ->label('Gudang')
                                            ->searchable()
                                            ->preload()
                                            ->options(function () {
                                                return Warehouse::where('status', 1)->pluck('name', 'id')->map(function ($name, $id) {
                                                    $warehouse = Warehouse::find($id);
                                                    return "({$warehouse->kode}) {$name}";
                                                });
                                            })
                                            ->required()
                                            ->validationMessages([
                                                'required' => 'Gudang wajib dipilih'
                                            ])
                                            ->default(function () {
                                                return Warehouse::where('status', 1)->first()?->id;
                                            })
                                            ->reactive()
                                            ->afterStateUpdated(function ($set) {
                                                $set('rak_id', null); // Reset rak when warehouse changes
                                            }),
                                        Select::make('rak_id')
                                            ->label('Rak')
                                            ->searchable(['code', 'name'])
                                            ->preload()
                                            ->options(function ($get) {
                                                $warehouseId = $get('warehouse_id');
                                                if ($warehouseId) {
                                                    return \App\Models\Rak::where('warehouse_id', $warehouseId)->pluck('name', 'id')->map(function ($name, $id) {
                                                        $rak = \App\Models\Rak::find($id);
                                                        return "({$rak->code}) {$name}";
                                                    });
                                                }
                                                return [];
                                            })
                                            ->nullable(),
                                        TextInput::make('discount')
                                            ->label('Discount (%)')
                                            ->numeric()
                                            ->default(function ($get, $record) {
                                                $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                return $quotationItem ? $quotationItem->discount : 0;
                                            })
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                $quantity = $get('quantity') ?? 0;
                                                $unitPrice = HelperController::parseIndonesianMoney($get('unit_price') ?? 0);
                                                $discount = $state ?? 0;
                                                $tax = $get('tax') ?? 0;
                                                $subtotal = HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $tax);
                                                $set('subtotal', $subtotal);
                                            }),
                                        TextInput::make('tax')
                                            ->label('Tax (%)')
                                            ->numeric()
                                            ->default(function ($get, $record) {
                                                $quotationItem = $record->quotationItem->where('product_id', $get('product_id'))->first();
                                                return $quotationItem ? $quotationItem->tax : 0;
                                            })
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                $quantity = $get('quantity') ?? 0;
                                                $unitPrice = HelperController::parseIndonesianMoney($get('unit_price') ?? 0);
                                                $discount = $get('discount') ?? 0;
                                                $tax = $state ?? 0;
                                                $subtotal = HelperController::hitungSubtotal($quantity, $unitPrice, $discount, $tax);
                                                $set('subtotal', $subtotal);
                                            }),
                                        TextInput::make('subtotal')
                                            ->label('Subtotal')
                                            ->numeric()
                                            ->readOnly()
                                            ->default(0),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(function ($record) {
                                        return $record && $record->quotationItem ? $record->quotationItem->count() : 0;
                                    })
                                    ->minItems(1)
                                    ->validationMessages([
                                        'minItems' => 'Minimal harus ada 1 item sales order'
                                    ])
                                    ->default(function ($record) {
                                        if ($record && $record->quotationItem) {
                                            $items = [];
                                            foreach ($record->quotationItem as $quotationItem) {
                                                $items[] = [
                                                    'product_id' => $quotationItem->product_id,
                                                    'quantity' => $quotationItem->quantity,
                                                    'unit_price' => $quotationItem->unit_price,
                                                    'discount' => $quotationItem->discount,
                                                    'tax' => $quotationItem->tax,
                                                    'warehouse_id' => null,
                                                    'rak_id' => null,
                                                    'subtotal' => $quotationItem->quantity * ($quotationItem->unit_price + $quotationItem->tax - $quotationItem->discount)
                                                ];
                                            }
                                            return $items;
                                        }
                                        return [];
                                    })
                                    ->columnSpanFull(),
                                Textarea::make('notes')
                                    ->label('Catatan')
                                    ->placeholder('Catatan tambahan untuk sales order (opsional)')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                    ])
                    ->action(function ($data, $record) {
                        $salesOrderService = app(SalesOrderService::class);

                        // Create sale order
                        $saleOrder = SaleOrder::create([
                            'customer_id' => $record->customer_id,
                            'quotation_id' => $record->id,
                            'so_number' => $data['so_number'],
                            'order_date' => $data['order_date'],
                            'delivery_date' => $data['delivery_date'],
                            'tipe_pengiriman' => $data['tipe_pengiriman'],
                            'status' => 'draft',
                            'total_amount' => $record->total_amount,
                            'created_by' => Auth::id(),
                            'reference_type' => 2, // Refer Quotation
                            'notes' => $data['notes'] ?? null,
                        ]);

                        // Create sale order items from form data
                        if (isset($data['saleOrderItems']) && is_array($data['saleOrderItems'])) {
                            foreach ($data['saleOrderItems'] as $item) {
                                $saleOrder->saleOrderItem()->create([
                                    'product_id' => $item['product_id'],
                                    'quantity' => $item['quantity'],
                                    'unit_price' => HelperController::parseIndonesianMoney($item['unit_price']),
                                    'discount' => $item['discount'] ?? 0,
                                    'tax' => $item['tax'] ?? 0,
                                    'warehouse_id' => $item['warehouse_id'],
                                    'rak_id' => $item['rak_id'] ?? null,
                                ]);
                            }
                        } else {
                            // Fallback to quotation items if repeater data is not available
                            foreach ($record->quotationItem as $quotationItem) {
                                $saleOrder->saleOrderItem()->create([
                                    'product_id' => $quotationItem->product_id,
                                    'quantity' => $quotationItem->quantity,
                                    'unit_price' => $quotationItem->unit_price,
                                    'discount' => $quotationItem->discount,
                                    'tax' => $quotationItem->tax,
                                    'warehouse_id' => 1, // Default warehouse
                                    'rak_id' => null,
                                ]);
                            }
                        }

                        // Update total amount
                        $salesOrderService->updateTotalAmount($saleOrder);

                        HelperController::sendNotification(isSuccess: true, title: "Success", message: "Sale Order {$data['so_number']} berhasil dibuat");

                        // Redirect to edit page
                        return redirect()->route('filament.admin.resources.sale-orders.edit', $saleOrder);
                    })
                    ->modalHeading('Buat Sales Order dari Quotation')
                    ->modalDescription('Buat sales order baru berdasarkan quotation ini. Periksa informasi dan isi nomor sales order.')
                    ->modalSubmitActionLabel('Buat Sales Order')
                    ->modalCancelActionLabel('Batal')
                    ->slideOver()
            ])->button()
        ];
    }
}
