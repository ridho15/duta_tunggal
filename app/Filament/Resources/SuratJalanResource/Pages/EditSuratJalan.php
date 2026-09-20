<?php

namespace App\Filament\Resources\SuratJalanResource\Pages;

use App\Filament\Resources\SuratJalanResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSuratJalan extends EditRecord
{
    protected static string $resource = SuratJalanResource::class;

    public function mount(int | string $record): void
    {
        // Surat Jalan yang sudah terbit/dibatalkan terkunci: beri penjelasan lalu arahkan ke halaman Lihat
        // (bukan 403 kosong).
        $model = $this->resolveRecord($record);

        if (! $model->isEditable()) {
            Notification::make()
                ->title('Surat Jalan Terkunci')
                ->body("Surat Jalan {$model->sj_number} berstatus \"{$model->status_label}\" sehingga tidak dapat diubah. Untuk koreksi gunakan Batalkan lalu Terbitkan Ulang; dokumen bertanda tangan dapat diunggah dari halaman Lihat.")
                ->warning()
                ->send();

            // Isi record supaya render (bila Livewire tetap merender saat redirect) tidak gagal pada tipe Model.
            $this->record = $model;
            $this->redirect(static::getResource()::getUrl('view', ['record' => $model]));

            return;
        }

        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return parent::mutateFormDataBeforeFill($data);
    }
}
