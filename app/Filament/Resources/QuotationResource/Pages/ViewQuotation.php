<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Http\Controllers\HelperController;
use App\Services\QuotationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;
    
    public function getTitle(): string
    {
        return 'View Quotation - ' . QuotationResource::quotationStatusLabel($this->record?->status);
    }

    protected function getActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make()
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn ($record) => $record->isEditable()),
                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->visible(fn ($record) => $record->isEditable()),
                QuotationResource::reviseAction(pageAction: true),
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
                    ->label('Ajukan Persetujuan')
                    ->icon('heroicon-o-arrow-uturn-up')
                    ->color('success')
                    ->hidden(function ($record) {
                        return !Auth::user()->hasPermissionTo('request-approve quotation') || $record->status != 'draft';
                    })
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $quotationService = app(QuotationService::class);
                        $quotationService->requestApprove($record);
                        HelperController::sendNotification(isSuccess: true, title: "Information", message: "Pengajuan persetujuan Quotation berhasil. Proses selanjutnya: Manajer Sales perlu mereview dan memberikan persetujuan atas Quotation ini.");
                    }),
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-badge')
                    ->hidden(function ($record) {
                        return $record->status != 'request_approve' || !Auth::user()->hasPermissionTo('approve quotation')
                            || !\App\Filament\Support\ApprovalActions::canApprove($record);
                    })
                    ->form(fn ($record) => \App\Filament\Support\ApprovalActions::overrideForm($record))
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record, array $data = []) {
                        try {
                            app(QuotationService::class)->approve($record, ['override_reason' => $data['override_reason'] ?? null]);
                        } catch (\Illuminate\Validation\ValidationException $e) {
                            HelperController::sendNotification(isSuccess: false, title: "Tidak Dapat Disetujui", message: collect($e->errors())->flatten()->implode(' '));

                            return;
                        }

                        HelperController::sendNotification(isSuccess: true, title: "Success", message: "Quotation berhasil disetujui. Proses selanjutnya: Tim Sales perlu membuat Sale Order berdasarkan Quotation yang telah disetujui ini.");
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->hidden(function ($record) {
                        return $record->status != 'request_approve' || !Auth::user()->hasPermissionTo('reject quotation');
                    })
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $quotationService = app(QuotationService::class);
                        $quotationService->reject($record);
                        HelperController::sendNotification(isSuccess: true, title: "Danger", message: "Quotation ditolak. Proses selanjutnya: Tim Sales perlu merevisi penawaran sesuai catatan penolakan dan mengajukan kembali untuk persetujuan.");
                    }),
                Action::make('sync_total_amount')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->label('Hitung Ulang Total')
                    ->color('primary')
                    ->action(function ($record) {
                        $quotationService = app(QuotationService::class);
                        $quotationService->updateTotalAmount($record);
                        HelperController::sendNotification(isSuccess: true, title: "Information", message: "Total berhasil di update");
                    }),
                Action::make('pdf_quotation')
                    ->label('Preview / Download PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn ($record) => route('pdf-stream', ['type' => 'quotation', 'id' => $record->id]))
                    ->openUrlInNewTab(),
                Action::make('create_sale_order')
                    ->label('Buat Sales Order')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->visible(fn ($record) => QuotationResource::canCreateSaleOrder($record))
                    // Skema modal & pembuatan SO dipakai bersama dengan aksi baris di tabel Quotation.
                    ->form(QuotationResource::saleOrderModalSchema())
                    ->action(function ($data, $record) {
                        // Perilaku (Draft / auto-approve) diatur satu tempat: config('sales.so_from_quotation_auto_approve').
                        return QuotationResource::runCreateSaleOrderAction($record, $data);
                    })
                    ->modalHeading('Buat Sales Order dari Quotation')
                    ->modalDescription('Buat sales order baru berdasarkan quotation ini. Periksa informasi dan isi nomor sales order.')
                    ->modalSubmitActionLabel('Buat Sales Order')
                    ->modalCancelActionLabel('Batal')
                    ->slideOver()
            ])->button()
        ];
    }

    public function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return QuotationResource::infolist($infolist);
    }
}
