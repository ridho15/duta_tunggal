<?php

namespace App\Filament\Resources\OrderRequestResource\Pages;

use App\Filament\Resources\OrderRequestResource;
use App\Filament\Resources\OrderRequestResource\Pages\Concerns\InteractsWithInlineOrderRequestItems;
use App\Http\Controllers\HelperController;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOrderRequest extends CreateRecord
{
    use InteractsWithInlineOrderRequestItems;

    protected static string $resource = OrderRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()->id;

        return OrderRequestResource::mutateFormDataBeforeCreate($data);
    }

    protected function afterCreate(): void
    {
        HelperController::sendNotification(
            isSuccess: true,
            title: 'Order Request Created',
            message: 'Order Request berhasil dibuat dengan nomor: ' . $this->record->request_number . '. Proses selanjutnya: Menunggu persetujuan dari Supervisor/Manager Purchasing.'
        );
    }
}
