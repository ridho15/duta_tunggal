<?php

namespace App\Filament\Resources\SuratJalanResource\Pages;

use App\Filament\Resources\SuratJalanResource;
use App\Models\DeliveryOrder;
use App\Models\SuratJalan;
use App\Services\SuratJalanService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateSuratJalan extends CreateRecord
{
    protected static string $resource = SuratJalanResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $deliveryOrderIds = $data['deliveryOrder'] ?? ($this->data['deliveryOrder'] ?? []);
        $deliveryOrderIds = is_array($deliveryOrderIds) ? $deliveryOrderIds : (empty($deliveryOrderIds) ? [] : [$deliveryOrderIds]);

        $deliveryOrders = DeliveryOrder::whereIn('id', $deliveryOrderIds)->get();

        // Aturan yang sama dengan Terbitkan Ulang: approved, satu cabang, belum di Surat Jalan lain yang berlaku.
        try {
            app(SuratJalanService::class)->assertDeliveryOrdersUsable($deliveryOrders, null, 'data.deliveryOrder');
        } catch (ValidationException $e) {
            $firstMessage = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            Notification::make()
                ->title('Gagal Membuat Surat Jalan')
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
            // Enforce branch inheritance from source Delivery Order(s)
            $data['cabang_id'] = (int) $sourceCabangIds->first();
        }

        $data['created_by'] = Auth::user()->id;
        $data['status'] = SuratJalan::STATUS_ISSUED;   // auto-terbit (J2), langsung terkunci
        return $data;
    }
}
