<?php

namespace App\Filament\Resources\ManufacturingOrderResource\Pages;

use App\Filament\Resources\ManufacturingOrderResource;
use App\Models\ProductionPlan;
use Filament\Resources\Pages\CreateRecord;

class CreateManufacturingOrder extends CreateRecord
{
    protected static string $resource = ManufacturingOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';

        // Enforce branch inheritance from Production Plan source
        if (!empty($data['production_plan_id'])) {
            $plan = ProductionPlan::with(['saleOrder', 'warehouse'])->find($data['production_plan_id']);
            if ($plan) {
                $inheritedCabangId = $plan->saleOrder?->cabang_id
                    ?? $plan->warehouse?->cabang_id
                    ?? null;
                if (!empty($inheritedCabangId)) {
                    $data['cabang_id'] = $inheritedCabangId;
                }
            }
        }

        return $data;
    }
}
