<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Http\Controllers\HelperController;
use App\Services\PurchaseOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected static ?string $title = 'Ubah Pembelian';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
            Action::make('konfirmasi')
                ->label('Konfirmasi')
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Admin') || in_array($record->status, ['draft', 'closed', 'approved', 'completed']);
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->action(function ($record) {
                    $record->update([
                        'status' => 'approved',
                        'date_approved' => Carbon::now(),
                        'approved_by' => Auth::user()->id,
                    ]);
                }),
            Action::make('tolak')
                ->label('Tolak')
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Admin') || in_array($record->status, ['draft', 'closed', 'approved', 'completed']);
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
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Owner') || in_array($record->status, ['request_approval', 'closed', 'completed', 'approved']);
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
                ->hidden(function ($record) {
                    return Auth::user()->hasRole('Owner') || in_array($record->status, ['request_close', 'closed', 'completed', 'approved']);
                })
                ->requiresConfirmation()
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->action(function ($record) {
                    $record->update([
                        'status' => 'request_close'
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
                    }, 'Pembelian_' . $record->po_number . '.pdf');
                }),
        ];
    }

    protected function afterSave()
    {
        $purchaseOrderService = app(PurchaseOrderService::class);
        $purchaseOrderService->updateTotalAmount($this->getRecord());
    }

    protected function getSubmitFormAction(): Action
    {
        return $this->getSaveFormAction()->icon('heroicon-o-pencil-square')->color('warning');
    }
}
