<?php

namespace App\Filament\Resources\OrderRequestResource\Pages;

use App\Filament\Resources\OrderRequestResource;
use App\Filament\Resources\OrderRequestResource\Pages\Concerns\InteractsWithInlineOrderRequestItems;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditOrderRequest extends EditRecord
{
    use InteractsWithInlineOrderRequestItems;

    protected static string $resource = OrderRequestResource::class;

    protected static string $view = 'filament.resources.order-request-resource.pages.edit-order-request';

    public function getViewData(): array
    {
        $apiController = app(\App\Http\Controllers\Api\OrderRequestApiController::class);
        $res = $apiController->dependencies(request());
        $initialData = $res->getData(true)['data'] ?? [];

        $recordData = $this->record->load([
            'orderRequestItem.product.uom',
            'orderRequestItem.supplier',
            'orderRequestItem.cabang',
            'currency',
        ])->toArray();

        return [
            'initialDependencies' => $initialData,
            'recordData' => $recordData,
        ];
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
        if (! empty($data['orderRequestItem']) && is_array($data['orderRequestItem'])) {
            foreach ($data['orderRequestItem'] as &$item) {
                $item['tipe_pajak'] = OrderRequestResource::normalizeItemTaxType($item['tipe_pajak'] ?? null);
            }
            unset($item);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->applyPendingInlineOrderRequestItemDrafts();
        $data['orderRequestItem'] = $this->data['orderRequestItem'] ?? ($data['orderRequestItem'] ?? []);

        if ($this->getRecord()->created_by == null) {
            $data['created_by'] = Auth::user()->id;
        }

        return OrderRequestResource::mutateFormDataBeforeSave($data);
    }

    protected function onValidationError(ValidationException $exception): void
    {
        $this->handleInlineOrderRequestValidationError($exception);

        parent::onValidationError($exception);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
