<?php

namespace App\Filament\Resources\StockTransferResource\Pages;

use App\Filament\Resources\StockTransferResource;
use App\Http\Controllers\HelperController;
use App\Services\StockTransferService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewStockTransfer extends ViewRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->visible(fn () => in_array($this->record->status, ['Draft', 'Request'], true)),
            Action::make('request_transfer')
                ->label('Request Transfer')
                ->color('success')
                ->icon('heroicon-o-arrow-down-circle')
                ->requiresConfirmation()
                ->visible(fn () => StockTransferResource::canRequestTransfer() && $this->record->status === 'Draft')
                ->action(function () {
                    try {
                        app(StockTransferService::class)->requestTransfer($this->record);
                        $this->refreshFormData(['status']);
                        HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Information',
                            message: 'Request stock transfer berhasil dikirimkan. Proses selanjutnya: Manajer Gudang atau Manajer Logistik perlu mereview dan menyetujui permintaan transfer stok ini.'
                        );
                    } catch (ValidationException $exception) {
                        HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Validasi Transfer Stok',
                            message: collect($exception->errors())->flatten()->implode("\n")
                        );
                    }
                }),
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->visible(fn () => StockTransferResource::canResponseTransfer() && $this->record->status === 'Request')
                ->action(function () {
                    try {
                        app(StockTransferService::class)->approveStockTransfer($this->record);
                        $this->refreshFormData(['status']);
                        HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Information',
                            message: 'Request transfer stock berhasil diapprove. Stok fisik berhasil dipindahkan antar lokasi gudang.'
                        );
                    } catch (ValidationException $exception) {
                        HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Validasi Transfer Stok',
                            message: collect($exception->errors())->flatten()->implode("\n")
                        );
                    }
                }),
            Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(fn () => StockTransferResource::canResponseTransfer() && $this->record->status === 'Request')
                ->action(function () {
                    try {
                        app(StockTransferService::class)->rejectTransfer($this->record);
                        $this->refreshFormData(['status']);
                        HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Information',
                            message: 'Request transfer stock ditolak. Pemohon perlu merevisi permintaan transfer sesuai keterangan penolakan.'
                        );
                    } catch (ValidationException $exception) {
                        HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Validasi Transfer Stok',
                            message: collect($exception->errors())->flatten()->implode("\n")
                        );
                    }
                }),
            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn () => in_array($this->record->status, ['Draft', 'Request', 'Reject'], true)),
        ];
    }
}
