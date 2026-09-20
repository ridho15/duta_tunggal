<?php

namespace App\Filament\Resources\SuratJalanResource\Pages;

use App\Filament\Resources\SuratJalanResource;
use App\Models\DeliveryOrder;
use App\Models\SuratJalan;
use App\Services\SuratJalanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSuratJalan extends CreateRecord
{
    protected static string $resource = SuratJalanResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $deliveryOrderIds = $data['deliveryOrder'] ?? [];
        $deliveryOrderIds = is_array($deliveryOrderIds) ? $deliveryOrderIds : [$deliveryOrderIds];

        $deliveryOrders = DeliveryOrder::whereIn('id', $deliveryOrderIds)->get();

        // Aturan yang sama dengan Terbitkan Ulang: approved, satu cabang, belum di Surat Jalan lain yang berlaku.
        app(SuratJalanService::class)->assertDeliveryOrdersUsable($deliveryOrders);

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
