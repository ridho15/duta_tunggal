<?php

namespace App\Filament\Resources\QualityControlManufactureResource\Pages;

use App\Filament\Resources\QualityControlManufactureResource;
use App\Models\Production;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQualityControlManufacture extends EditRecord
{
    protected static string $resource = QualityControlManufactureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['from_model_type'] = Production::class;

        if (! QualityControlManufactureResource::canChooseInspector()) {
            $data['inspected_by'] = $this->record->inspected_by;
        }

        $production = Production::query()
            ->with('manufacturingOrder.productionPlan.product')
            ->find($data['from_model_id'] ?? $this->record->from_model_id);

        if ($production) {
            $context = QualityControlManufactureResource::resolveProductionContext($production);

            $data['product_id'] = $data['product_id'] ?? $this->record->product_id ?? $context['product_id'];
            $data['warehouse_id'] = $data['warehouse_id'] ?? $this->record->warehouse_id ?? $context['warehouse_id'];
            $data['rak_id'] = $data['rak_id'] ?? $this->record->rak_id ?? $context['rak_id'];
            $data['cabang_id'] = $data['cabang_id'] ?? $this->record->cabang_id ?? $context['cabang_id'];
        }

        return $data;
    }
}
