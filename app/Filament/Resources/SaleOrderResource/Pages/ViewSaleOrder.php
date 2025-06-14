<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use App\Http\Controllers\HelperController;
use App\Services\SalesOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewSaleOrder extends ViewRecord
{
    protected static string $resource = SaleOrderResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
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
                    $salesOrderService->approve($record);
                    HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan approve sale order");
                }),
            Action::make('closed')
                ->label('Close')
                ->requiresConfirmation()
                ->color('warning')
                ->icon('heroicon-o-x-circle')
                ->visible(function ($record) {
                    return Auth::user()->hasPermissionTo('response sales order') && ($record->status == 'request_close');
                })
                ->action(function ($record) {
                    $salesOrderService = app(SalesOrderService::class);
                    $salesOrderService->close($record);
                    HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order Closed");
                }),
            Action::make('reject')
                ->label('Reject')
                ->requiresConfirmation()
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(function ($record) {
                    return Auth::user()->hasPermissionTo('response sales order') && ($record->status == 'request_approve');
                })
                ->action(function ($record) {
                    $salesOrderService = app(SalesOrderService::class);
                    $salesOrderService->reject($record);
                    HelperController::sendNotification(isSuccess: true, title: "Information", message: "Melakukan Reject Sale");
                }),
            Action::make('pdf_sale_order')
                ->label('Download PDF')
                ->color('danger')
                ->visible(function ($record) {
                    return $record->status == 'approved' || $record->status == 'completed' || $record->status == 'confirmed' || $record->status == 'received';
                })
                ->icon('heroicon-o-document')
                ->action(function ($record) {
                    $pdf = Pdf::loadView('pdf.sales-order', [
                        'saleOrder' => $record
                    ])->setPaper('A4', 'potrait');

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->stream();
                    }, 'Sale_Order_' . $record->so_number . '.pdf');
                }),
            Action::make('completed')
                ->label('Complete')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->visible(function ($record) {
                    return Auth::user()->hasRole(['Super Admin', 'Owner']) && ($record->status != 'closed' || $record->status != 'reject');
                })
                ->color('success')
                ->action(function ($record) {
                    $salesOrderService = app(SalesOrderService::class);
                    $salesOrderService->completed($record);

                    HelperController::sendNotification(isSuccess: true, title: "Information", message: "Sales Order Completed");
                }),
            Action::make('btn_titip_saldo')
                ->label('Saldo Titip Customer')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->form([
                    TextInput::make('titip_saldo')
                        ->numeric()
                        ->prefix('Rp.')
                        ->required()
                        ->default(0),
                ])
                ->action(function (array $data, $record) {
                    $record->update([
                        'titip_saldo' => $data['titip_saldo'],
                    ]);
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
        ];
    }
}
