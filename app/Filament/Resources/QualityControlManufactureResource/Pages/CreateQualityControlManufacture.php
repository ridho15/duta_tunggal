<?php

namespace App\Filament\Resources\QualityControlManufactureResource\Pages;

use App\Filament\Resources\QualityControlManufactureResource;
use App\Models\Production;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateQualityControlManufacture extends CreateRecord
{
    protected static string $resource = QualityControlManufactureResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['from_model_type'] = Production::class;

        if (! QualityControlManufactureResource::canChooseInspector()) {
            $data['inspected_by'] = Auth::id();
        }

        $production = Production::query()
            ->with('manufacturingOrder.productionPlan.product')
            ->find($data['from_model_id'] ?? null);

        if ($production) {
            $context = QualityControlManufactureResource::resolveProductionContext($production);

            $data['product_id'] = $data['product_id'] ?? $context['product_id'];
            $data['warehouse_id'] = $data['warehouse_id'] ?? $context['warehouse_id'];
            $data['rak_id'] = $data['rak_id'] ?? $context['rak_id'];
            $data['passed_quantity'] = $data['passed_quantity'] ?? $context['passed_quantity'];
            $data['rejected_quantity'] = $data['rejected_quantity'] ?? $context['rejected_quantity'];
            $data['cabang_id'] = $data['cabang_id'] ?? $context['cabang_id'];
        }

        return $data;
    }
}
