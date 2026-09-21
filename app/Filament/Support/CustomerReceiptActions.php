<?php

namespace App\Filament\Support;

use App\Models\CustomerReceipt;
use App\Services\CustomerReceiptCancellation;
use App\Services\DocumentLock;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** Aksi "Batalkan Penerimaan" (T3.3, D29) — koreksi resmi penerimaan berjurnal; dipakai tabel dan halaman View. */
class CustomerReceiptActions
{
    public static function cancel($action)
    {
        return $action
            ->label('Batalkan Penerimaan')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Batalkan Penerimaan')
            ->modalDescription('Jurnal penerimaan dibalik (entri cermin, dokumen asli tetap tersimpan), piutang dan status invoice dikembalikan. Tidak dapat dibatalkan dua kali.')
            ->modalSubmitActionLabel('Ya, Batalkan Penerimaan')
            ->extraAttributes(['wire:loading.attr' => 'disabled'])
            ->form([
                Textarea::make('reason')->label('Alasan pembatalan')->required()->minLength(10)->rows(3),
            ])
            ->visible(fn (CustomerReceipt $record): bool => DocumentLock::enabled()
                && (bool) Auth::user()?->hasPermissionTo('delete customer receipt')
                && app(CustomerReceiptCancellation::class)->blocker($record) === null)
            ->action(function (CustomerReceipt $record, array $data): void {
                try {
                    app(CustomerReceiptCancellation::class)->cancel($record, (string) ($data['reason'] ?? ''), Auth::user());
                    Notification::make()->success()->title('Penerimaan Dibatalkan')->body('Jurnal dibalik dan piutang dikembalikan.')->send();
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Tidak Dapat Dibatalkan')->body(collect($e->errors())->flatten()->implode(' '))->persistent()->send();
                }
            });
    }
}
