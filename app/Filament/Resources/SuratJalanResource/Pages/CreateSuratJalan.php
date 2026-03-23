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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $deliveryOrderIds = $data['deliveryOrder'] ?? [];
        $deliveryOrderIds = is_array($deliveryOrderIds) ? $deliveryOrderIds : [$deliveryOrderIds];

        $invalidCount = DeliveryOrder::whereIn('id', $deliveryOrderIds)
            ->where('status', '!=', 'approved')
            ->count();

        if ($invalidCount > 0) {
            throw ValidationException::withMessages([
                'deliveryOrder' => 'Surat Jalan hanya dapat dibuat dari Delivery Order berstatus approved.',
            ]);
        }

        $data['created_by'] = Auth::user()->id;
        $data['status'] = 1;
        return $data;
    }
}
