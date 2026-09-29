<?php

namespace App\Filament\Resources\StockTransferResource\Pages;

use App\Filament\Resources\StockTransferResource;
use App\Http\Controllers\HelperController;
use App\Services\StockTransferService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditStockTransfer extends EditRecord
{
    protected static string $resource = StockTransferResource::class;

    protected static bool $canUseDatabaseTransactions = true;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! isset($data['stockTransferItem']) || ! is_array($data['stockTransferItem']) || count($data['stockTransferItem']) === 0) {
            throw ValidationException::withMessages([
                'stockTransferItem' => 'Minimal harus menambahkan 1 item untuk transfer stok.',
            ]);
        }

        foreach ($data['stockTransferItem'] as $idx => &$item) {
            if (empty($item['from_rak_id'])) {
                $item['from_rak_id'] = null;
            }
            if (empty($item['to_rak_id'])) {
                $item['to_rak_id'] = null;
            }
        }
        unset($item);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->icon('heroicon-o-eye'),
            Action::make('request_transfer')
                ->label('Request Transfer')
                ->color('success')
                ->icon('heroicon-o-arrow-down-circle')
                ->requiresConfirmation()
                ->visible(fn () => StockTransferResource::canRequestTransfer() && $this->record->status === 'Draft')
                ->action(function () {
                    try {
                        $this->save();
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
            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn () => in_array($this->record->status, ['Draft', 'Request', 'Reject'], true)),
        ];
    }
}
