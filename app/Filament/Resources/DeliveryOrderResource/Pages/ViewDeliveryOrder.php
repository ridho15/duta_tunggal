<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewDeliveryOrder extends ViewRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->visible(function () {
                    $record = $this->record;
                    return Auth::user()->hasPermissionTo('update delivery order') &&
                        in_array($record->status, ['draft', 'request_approve', 'request_close']);
                }),
            DeleteAction::make()
                ->visible(function () {
                    $record = $this->record;
                    return Auth::user()->hasPermissionTo('delete delivery order') &&
                        $record->status == 'draft';
                }),

            Actions\Action::make('request_stock')
                ->label('Request Stock ke Gudang')
                ->requiresConfirmation()
                ->modalHeading('Request Stock ke Gudang')
                ->modalDescription('Ini akan membuat Konfirmasi Gudang untuk setiap item dan mengubah status DO menjadi Request Stock.')
                ->color('warning')
                ->icon('heroicon-o-building-storefront')
                ->visible(function () {
                    $record = $this->record;
                    return Auth::user()->hasPermissionTo('request delivery order') &&
                        $record->status === 'draft';
                })
                ->action(function ($record) {
                    $record->load('deliveryOrderItem.product');
                    // Create one WC covering all DO items
                    $wc = \App\Models\WarehouseConfirmation::create([
                        'delivery_order_id' => $record->id,
                        'confirmation_type' => 'sales_order',
                        'status' => 'request',
                        'confirmed_by' => Auth::id(),
                        'note' => 'Auto-created dari DO ' . $record->do_number,
                    ]);
                    foreach ($record->deliveryOrderItem as $item) {
                        $wc->warehouseConfirmationItems()->create([
                            'sale_order_item_id' => $item->sale_order_item_id,
                            'product_name' => $item->product->name ?? '-',
                            'requested_qty' => $item->quantity,
                            'confirmed_qty' => $item->quantity,
                            'warehouse_id' => null,
                            'status' => 'request',
                        ]);
                    }
                    $record->update(['status' => 'request_stock']);
                    \App\Http\Controllers\HelperController::sendNotification(
                        isSuccess: true,
                        title: 'Information',
                        message: 'Request Stock telah dikirim ke Gudang. Proses selanjutnya: Konfirmasi oleh Staf Gudang.'
                    );
                }),

            Actions\Action::make('request_approve')
                ->label('Request Approve')
                ->requiresConfirmation()
                ->color('success')
                ->icon('heroicon-o-arrow-uturn-up')
                ->visible(function () {
                    $record = $this->record;
                    return Auth::user()->hasPermissionTo('request delivery order') &&
                        $record->status == 'draft';
                })
                ->action(function ($record) {
                    $deliveryOrderService = app(\App\Services\DeliveryOrderService::class);
                    $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'request_approve');
                    \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Information", message: "Delivery Order telah diajukan untuk persetujuan. Proses selanjutnya: Persetujuan oleh Manajer Logistik/Finance.");
                }),
            Actions\Action::make('request_close')
                ->label('Request Close')
                ->requiresConfirmation()
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(function () {
                    $record = $this->record;
                    return Auth::user()->hasPermissionTo('request delivery order') &&
                        in_array($record->status, ['draft', 'request_approve', 'request_close']);
                })
                ->action(function ($record) {
                    $deliveryOrderService = app(\App\Services\DeliveryOrderService::class);
                    $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'request_close');
                    \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Information", message: "Permintaan penutupan Delivery Order telah diajukan. Proses selanjutnya: Konfirmasi penutupan oleh Manajer Logistik.");
                }),

            Actions\Action::make('approve')
                ->label('Approve Delivery Order')
                ->requiresConfirmation()
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->visible(function () {
                    $record = $this->record;
                    return Auth::user()->hasPermissionTo('response delivery order') &&
                        $record->status == 'request_approve' &&
                        $record->suratJalan()->exists();
                })
                ->form([
                    \Filament\Forms\Components\Textarea::make('comments')
                        ->label('Comments')
                        ->placeholder('Optional approval comments...')
                        ->nullable()
                ])
                ->action(function ($record, array $data) {
                    try {
                        $deliveryOrderService = app(\App\Services\DeliveryOrderService::class);
                        $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'approved', comments: $data['comments'] ?? null, action: 'approved');
                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Information", message: "Delivery Order telah disetujui. Proses selanjutnya: Pengiriman barang oleh Driver melalui Surat Jalan.");
                    } catch (\Exception $e) {
                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: false, title: "Error", message: $e->getMessage());
                        throw $e;
                    }
                }),
            Actions\Action::make('reject')
                ->label('Reject Delivery Order')
                ->requiresConfirmation()
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(function () {
                    $record = $this->record;
                    return Auth::user()->hasPermissionTo('response delivery order') &&
                        $record->status == 'request_approve';
                })
                ->form([
                    \Filament\Forms\Components\Textarea::make('comments')
                        ->label('Rejection Reason')
                        ->placeholder('Please provide reason for rejection...')
                        ->required()
                ])
                ->action(function ($record, array $data) {
                    $deliveryOrderService = app(\App\Services\DeliveryOrderService::class);
                    $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'reject', comments: $data['comments'], action: 'rejected');
                    \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Information", message: "Delivery Order telah ditolak. Proses selanjutnya: Tim Logistik perlu memperbaiki data Delivery Order sesuai alasan penolakan dan mengajukan kembali untuk persetujuan.");
                }),
            Actions\Action::make('closed')
                ->label('Close')
                ->requiresConfirmation()
                ->color('warning')
                ->icon('heroicon-o-x-circle')
                ->visible(function () {
                    $record = $this->record;
                    return Auth::user()->hasPermissionTo('response delivery order') &&
                        $record->status == 'request_close';
                })
                ->action(function ($record) {
                    $deliveryOrderService = app(\App\Services\DeliveryOrderService::class);
                    $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'closed');
                    \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Information", message: "Delivery Order telah ditutup. Proses selanjutnya: Tim Finance perlu memastikan Invoice telah diterbitkan dan diselesaikan untuk Delivery Order ini.");
                }),

            Actions\Action::make('sent')
                ->label('Mark as Sent')
                ->requiresConfirmation()
                ->modalHeading('Mark Delivery Order as Sent')
                ->modalDescription('Are you sure you want to mark this delivery order as sent? This will create journal entries for goods delivery.')
                ->modalSubmitActionLabel('Yes, Mark as Sent')
                ->color('info')
                ->icon('heroicon-o-paper-airplane')
                ->visible(function () {
                    $record = $this->record;
                    return Auth::user()->hasPermissionTo('response delivery order') &&
                        $record->status == 'approved';
                })
                ->action(function ($record) {
                    try {
                        $deliveryOrderService = app(\App\Services\DeliveryOrderService::class);
                        $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'sent');
                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Success", message: "Delivery Order telah ditandai sebagai terkirim. Proses selanjutnya: Konfirmasi penerimaan oleh Tim Sales/Admin.");
                    } catch (\Exception $e) {
                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: false, title: "Error", message: $e->getMessage());
                        throw $e;
                    }
                }),
            Actions\Action::make('completed')
                ->label('Complete')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->color('success')
                ->visible(function () {
                    $record = $this->record;
                    return Auth::user()->hasPermissionTo('response delivery order') &&
                        $record->status == 'sent';
                })
                ->action(function ($record) {
                    $deliveryOrderService = app(\App\Services\DeliveryOrderService::class);
                    $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'completed');
                    // Post delivery order to general ledger for HPP recognition
                    $postResult = $deliveryOrderService->postDeliveryOrder($record);
                    if ($postResult['status'] === 'posted') {
                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Information", message: "Delivery Order selesai dan telah diposting ke buku besar. Proses selanjutnya: Penerbitan Invoice oleh Tim Finance.");
                    } elseif ($postResult['status'] === 'error') {
                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: false, title: "Error", message: "Sales Order Completed but posting failed: " . $postResult['message']);
                    } else {
                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Information", message: "Delivery Order selesai. Proses selanjutnya: Penerbitan Invoice oleh Tim Finance.");
                    }
                }),

            Actions\Action::make('checker_edit_quantity')
                ->label('Checker Edit Qty')
                ->color('warning')
                ->icon('heroicon-o-pencil-square')
                ->visible(function () {
                    $record = $this->record;
                    return ($record->status == 'approved' || $record->status == 'confirmed') &&
                        Auth::user()->hasRole(['Checker', 'Super Admin', 'Owner', 'Admin']);
                })
                ->form([
                    \Filament\Forms\Components\Fieldset::make('Edit Quantity untuk Checker')
                        ->schema([
                            \Filament\Forms\Components\Repeater::make('delivery_items')
                                ->label('Delivery Order Items')
                                ->schema([
                                    \Filament\Forms\Components\TextInput::make('product_name')
                                        ->label('Product')
                                        ->disabled()
                                        ->columnSpan(2),
                                    \Filament\Forms\Components\TextInput::make('original_quantity')
                                        ->label('Qty Asli')
                                        ->disabled()
                                        ->numeric(),
                                    \Filament\Forms\Components\TextInput::make('current_quantity')
                                        ->label('Qty Saat Ini')
                                        ->disabled()
                                        ->numeric(),
                                    \Filament\Forms\Components\TextInput::make('new_quantity')
                                        ->label('Qty Baru')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->rules([
                                            function (\Filament\Forms\Get $get) {
                                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                    $currentQty = $get('current_quantity');
                                                    if ($value > $currentQty) {
                                                        $fail("Quantity baru tidak boleh lebih besar dari quantity saat ini ({$currentQty})");
                                                    }
                                                };
                                            },
                                        ]),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->defaultItems(0)
                                ->itemLabel('Delivery Item')
                                ->addable(false)
                                ->deletable(false)
                                ->mutateDehydratedStateUsing(function ($state) {
                                    // Pastikan semua item memiliki key yang diperlukan
                                    return collect($state)->map(function ($item) {
                                        return array_merge($item, [
                                            'delivery_order_item_id' => $item['delivery_order_item_id'] ?? null,
                                            'new_quantity' => $item['new_quantity'] ?? 0,
                                            'current_quantity' => $item['current_quantity'] ?? 0,
                                        ]);
                                    })->values()->toArray();
                                })
                                ->default(function () {
                                    $record = $this->record;
                                    $items = [];
                                    foreach ($record->deliveryOrderItem as $item) {
                                        $items[] = [
                                            'delivery_order_item_id' => $item->id,
                                            'product_name' => $item->product->name,
                                            'original_quantity' => $item->quantity,
                                            'current_quantity' => $item->quantity,
                                            'new_quantity' => $item->quantity,
                                        ];
                                    }
                                    return $items;
                                }),
                            \Filament\Forms\Components\Textarea::make('checker_notes')
                                ->label('Catatan Checker')
                                ->placeholder('Masukkan alasan perubahan quantity...')
                                ->nullable(),
                        ])
                ])
                ->action(function ($record, array $data) {
                    $deliveryOrderService = app(\App\Services\DeliveryOrderService::class);

                    // Pastikan delivery_items ada dan merupakan array
                    $deliveryItems = $data['delivery_items'] ?? [];

                    // Update quantity untuk setiap item yang diubah
                    foreach ($deliveryItems as $itemData) {
                        // Pastikan semua key yang diperlukan ada
                        $deliveryOrderItemId = $itemData['delivery_order_item_id'] ?? null;
                        $newQuantity = $itemData['new_quantity'] ?? 0;
                        $currentQuantity = $itemData['current_quantity'] ?? 0;

                        if ($deliveryOrderItemId && $newQuantity != $currentQuantity) {
                            $deliveryItem = $record->deliveryOrderItem()->find($deliveryOrderItemId);
                            if ($deliveryItem) {
                                $oldQuantity = $deliveryItem->quantity;
                                $deliveryItem->update(['quantity' => $newQuantity]);

                                // Update remaining quantity di sale order item
                                if ($deliveryItem->saleOrderItem) {
                                    $saleOrderItem = $deliveryItem->saleOrderItem;
                                    $quantityDifference = $oldQuantity - $newQuantity;
                                    $saleOrderItem->increment('remaining_quantity', $quantityDifference);
                                }
                            }
                        }
                    }

                    \App\Http\Controllers\HelperController::sendNotification(
                        isSuccess: true,
                        title: "Success",
                        message: "Quantity Delivery Order berhasil diupdate oleh Checker"
                    );
                }),

            Action::make('surat_jalan_status')
                ->label(function () {
                    $record = $this->record;
                    return $record->suratJalan()->exists() ? 'Surat Jalan: Ada' : 'Surat Jalan: Belum Ada';
                })
                ->color(function () {
                    $record = $this->record;
                    return $record->suratJalan()->exists() ? 'success' : 'warning';
                })
                ->icon(function () {
                    $record = $this->record;
                    return $record->suratJalan()->exists() ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle';
                })
                ->disabled()
                ->visible(function () {
                    $record = $this->record;
                    return in_array($record->status, ['approved', 'completed', 'confirmed', 'received', 'sent']);
                })
                ->tooltip(function () {
                    $record = $this->record;
                    if ($record->suratJalan()->exists()) {
                        $suratJalan = $record->suratJalan()->where('status', 1)->first() ?? $record->suratJalan()->first();
                        if ($suratJalan) {
                            return "Surat Jalan: {$suratJalan->sj_number} - Status: {$suratJalan->status}";
                        }
                    }
                    return 'Delivery Order belum memiliki Surat Jalan. Surat Jalan diperlukan sebelum approval.';
                }),
            Action::make('pdf_delivery_order')
                ->label('Download PDF')
                ->color('danger')
                ->icon('heroicon-o-document')
                ->visible(function () {
                    $record = $this->record;
                    return in_array($record->status, ['approved', 'completed', 'confirmed', 'received', 'sent']);
                })
                ->action(function ($record) {
                    // Load necessary relationships for PDF
                    $record->load([
                        'cabang',
                        'deliveryOrderItem.product',
                        'deliveryOrderItem.saleOrderItem',
                        'salesOrders.customer'
                    ]);

                    $pdf = Pdf::loadView('pdf.delivery-order', [
                        'deliveryOrder' => $record
                    ])->setPaper('A4', 'potrait');

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->stream();
                    }, 'Delivery_Order_' . $record->do_number . '.pdf');
                }),
        ];
    }
}
