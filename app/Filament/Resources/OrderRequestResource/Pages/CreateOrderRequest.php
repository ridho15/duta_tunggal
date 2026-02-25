<?php

namespace App\Filament\Resources\OrderRequestResource\Pages;

use App\Filament\Resources\OrderRequestResource;
use App\Http\Controllers\HelperController;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateOrderRequest extends CreateRecord
{
    protected static string $resource = OrderRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set cabang_id if not provided
        if (empty($data['cabang_id'])) {
            $user = Auth::user();
            $data['cabang_id'] = $user?->cabang_id;
        }

        $data['created_by'] = Auth::user()->id;
        return $data;
    }

    protected function afterCreate(): void
    {
        HelperController::sendNotification(
            isSuccess: true,
            title: 'Order Request Created',
            message: 'Order Request berhasil dibuat dengan nomor: ' . $this->record->request_number
        );
    }
}
