<?php

namespace App\Filament\Resources\SuratJalanResource\Pages;

use App\Filament\Resources\SuratJalanResource;
use App\Models\DeliveryOrder;
use Filament\Actions;
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
        $deliveryOrderIds = $data['deliveryOrder'] ?? [];
        $deliveryOrderIds = is_array($deliveryOrderIds) ? $deliveryOrderIds : [$deliveryOrderIds];

        $deliveryOrders = DeliveryOrder::whereIn('id', $deliveryOrderIds)->get();

        $invalidCount = $deliveryOrders->where('status', '!=', 'approved')->count();

        if ($invalidCount > 0) {
            throw ValidationException::withMessages([
                'deliveryOrder' => 'Surat Jalan hanya dapat dibuat dari Delivery Order berstatus approved.',
            ]);
        }

        $sourceCabangIds = $deliveryOrders
            ->pluck('cabang_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        if (count($sourceCabangIds) > 1) {
            throw ValidationException::withMessages([
                'deliveryOrder' => 'Semua Delivery Order yang dipilih harus berasal dari cabang yang sama.',
            ]);
        }

        if (!empty($sourceCabangIds)) {
            // Enforce branch inheritance from source Delivery Order(s)
            $data['cabang_id'] = $sourceCabangIds[0];
        }

        $data['created_by'] = Auth::user()->id;
        $data['status'] = 1;
        return $data;
    }
}
