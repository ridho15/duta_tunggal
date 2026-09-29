<?php

namespace App\Filament\Resources\SuratJalanResource\Pages;

use App\Filament\Resources\SuratJalanResource;
use App\Models\DeliveryOrder;
use App\Services\SuratJalanService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $deliveryOrderIds = $data['deliveryOrder'] ?? ($this->data['deliveryOrder'] ?? []);
        $deliveryOrderIds = is_array($deliveryOrderIds) ? $deliveryOrderIds : (empty($deliveryOrderIds) ? [] : [$deliveryOrderIds]);

        $deliveryOrders = DeliveryOrder::whereIn('id', $deliveryOrderIds)->get();

        try {
            app(SuratJalanService::class)->assertDeliveryOrdersUsable($deliveryOrders, $this->record->id, 'data.deliveryOrder');
        } catch (ValidationException $e) {
            $firstMessage = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            Notification::make()
                ->title('Gagal Mengubah Surat Jalan')
                ->body($firstMessage)
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'data.deliveryOrder' => $firstMessage,
                'deliveryOrder' => $firstMessage,
            ]);
        }

        $sourceCabangIds = $deliveryOrders->pluck('cabang_id')->filter()->unique()->values();
        if ($sourceCabangIds->isNotEmpty()) {
            $data['cabang_id'] = (int) $sourceCabangIds->first();
        }

        return $data;
    }
}
