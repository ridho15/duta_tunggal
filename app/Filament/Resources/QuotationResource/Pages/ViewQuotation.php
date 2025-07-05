<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Http\Controllers\HelperController;
use App\Services\QuotationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

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
                Action::make('pdf_quotation')
                    ->label('PDF Quotation')
                    ->color('danger')
                    ->icon('heroicon-o-document')
                    ->hidden(function ($record) {
                        return $record->status != 'approve';
                    })
                    ->action(function ($record) {
                        $pdf = Pdf::loadView('pdf.quotation', [
                            'quotation' => $record
                        ])->setPaper('A4', 'potrait');

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->stream();
                        }, 'Quotation_' . $record->quotation_number . '.pdf');
                    }),
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
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('request-approve quotation') && $record->status == 'draft';
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
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('reject quotation') && ($record->status == 'draft');
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
                    ->visible(function ($record) {
                        return Auth::user()->hasPermissionTo('reject quotation') && ($record->status == 'draft');
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
                    })
            ])->button()
                ->label('Action')
        ];
    }
}
