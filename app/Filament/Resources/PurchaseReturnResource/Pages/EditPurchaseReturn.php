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
            \Filament\Actions\Action::make('submit_for_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'draft')
                ->action(function () {
                    try {
                        $service = app(\App\Services\PurchaseReturnService::class);
                        $service->submitForApproval($this->record);
                        $this->refreshFormData(['status']);
                        \Filament\Notifications\Notification::make()
                            ->title('Retur pembelian berhasil diajukan')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        \App\Support\ProcurementFailureNotifier::danger(
                            'Gagal Mengajukan Retur',
                            $exception,
                            'Retur pembelian belum berhasil diajukan.'
                        );
                    }
                }),
            \Filament\Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, ['draft', 'pending_approval']))
                ->form([
                    \Filament\Forms\Components\Textarea::make('approval_notes')
                        ->label('Approval Notes')
                        ->nullable(),
                ])
                ->action(function (array $data) {
                    try {
                        $service = app(\App\Services\PurchaseReturnService::class);
                        $service->approve($this->record, $data);
                        \Filament\Notifications\Notification::make()
                            ->title('Retur pembelian berhasil disetujui')
                            ->success()
                            ->send();
                        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (Throwable $exception) {
                        \App\Support\ProcurementFailureNotifier::danger(
                            'Gagal Menyetujui Retur',
                            $exception,
                            'Retur pembelian belum dapat disetujui.'
                        );
                    }
                }),
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
