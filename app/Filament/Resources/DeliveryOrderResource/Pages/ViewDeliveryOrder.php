<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\Action;
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
                ->icon('heroicon-o-pencil-square'),
            Actions\Action::make('approve')
                ->label('Approve Delivery Order')
                ->requiresConfirmation()
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->visible(function ($record) {
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
                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan approve Delivery Order");
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
                ->visible(function ($record) {
                    return Auth::user()->hasPermissionTo('response delivery order') && $record->status == 'request_approve';
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
                    \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan Reject Delivery Order");
                }),
            Actions\Action::make('sent')
                ->label('Mark as Sent')
                ->requiresConfirmation()
                ->modalHeading('Mark Delivery Order as Sent')
                ->modalDescription('Are you sure you want to mark this delivery order as sent? This will create journal entries for goods delivery.')
                ->modalSubmitActionLabel('Yes, Mark as Sent')
                ->color('info')
                ->icon('heroicon-o-paper-airplane')
                ->visible(function ($record) {
                    return Auth::user()->hasPermissionTo('response delivery order') &&
                           $record->status == 'approved';
                })
                ->action(function ($record) {
                    try {
                        $deliveryOrderService = app(\App\Services\DeliveryOrderService::class);
                        $deliveryOrderService->updateStatus(deliveryOrder: $record, status: 'sent');
                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: true, title: "Success", message: "Delivery Order marked as sent successfully");
                    } catch (\Exception $e) {
                        \App\Http\Controllers\HelperController::sendNotification(isSuccess: false, title: "Error", message: $e->getMessage());
                        throw $e;
                    }
                }),
            Action::make('surat_jalan_status')
                ->label(function ($record) {
                    return $record->suratJalan()->exists() ? 'Surat Jalan: Ada' : 'Surat Jalan: Belum Ada';
                })
                ->color(function ($record) {
                    return $record->suratJalan()->exists() ? 'success' : 'warning';
                })
                ->icon(function ($record) {
                    return $record->suratJalan()->exists() ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle';
                })
                ->disabled()
                ->tooltip(function ($record) {
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
        ];
    }
}
