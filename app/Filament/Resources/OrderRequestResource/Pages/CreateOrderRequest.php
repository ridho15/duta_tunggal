<?php

namespace App\Filament\Resources\OrderRequestResource\Pages;

use App\Filament\Resources\OrderRequestResource;
use App\Filament\Resources\OrderRequestResource\Pages\Concerns\InteractsWithInlineOrderRequestItems;
use App\Http\Controllers\HelperController;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateOrderRequest extends CreateRecord
{
    use InteractsWithInlineOrderRequestItems;

    protected static string $resource = OrderRequestResource::class;

    protected static string $view = 'filament.resources.order-request-resource.pages.create-order-request';

    public function getViewData(): array
    {
        $apiController = app(\App\Http\Controllers\Api\OrderRequestApiController::class);
        $res = $apiController->dependencies(request());
        $initialData = $res->getData(true)['data'] ?? [];

        return [
            'initialDependencies' => $initialData,
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->applyPendingInlineOrderRequestItemDrafts();

        $data['orderRequestItem'] = $this->data['orderRequestItem'] ?? ($data['orderRequestItem'] ?? []);
        $data['created_by'] = Auth::user()->id;

        return OrderRequestResource::mutateFormDataBeforeCreate($data);
    }

    protected function onValidationError(ValidationException $exception): void
    {
        $this->handleInlineOrderRequestValidationError($exception);

        parent::onValidationError($exception);
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
