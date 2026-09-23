<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditPurchaseReturn extends EditRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        if (!in_array($this->record->status, ['draft', 'rejected'])) {
            \Filament\Notifications\Notification::make()
                ->title('Akses Ditolak')
                ->body('Retur pembelian dengan status "' . $this->record->status . '" telah dikunci dan tidak dapat diubah lagi.')
                ->warning()
                ->send();

            $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn () => in_array($this->record->status, ['draft', 'rejected'])),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('EditPurchaseReturn handleRecordUpdate failed', [
                'purchase_return_id' => $record->id,
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::danger(
                'Gagal Memperbarui Retur Pembelian',
                $exception,
                'Perubahan retur pembelian belum berhasil disimpan. Periksa kembali data retur lalu coba lagi.'
            );

            throw $exception;
        }
    }
}
