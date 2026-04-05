<?php

namespace App\Filament\Resources\ProductionPlanResource\Pages;

use App\Filament\Resources\ProductionPlanResource;
use App\Models\BillOfMaterial;
use App\Models\SaleOrder;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionPlan extends EditRecord
{
    protected static string $resource = ProductionPlanResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['source_type'] ?? null) === 'sale_order' && ! empty($data['sale_order_id'])) {
            $saleOrder = SaleOrder::with(['saleOrderItem.product'])->find($data['sale_order_id']);

            if ($saleOrder?->cabang_id) {
                $data['cabang_id'] = $saleOrder->cabang_id;
            }

            $data['bill_of_material_id'] = null;

            if ($saleOrder?->saleOrderItem?->isNotEmpty()) {
                $item = $saleOrder->saleOrderItem->first();
                $data['product_id'] = $item?->product_id;
                $data['quantity'] = $item?->quantity ?? $data['quantity'] ?? null;
                $data['uom_id'] = $item?->product?->uom_id ?? $data['uom_id'] ?? null;
            }
        }

        if (($data['source_type'] ?? null) === 'manual' && ! empty($data['bill_of_material_id'])) {
            $billOfMaterial = BillOfMaterial::with(['product'])->find($data['bill_of_material_id']);

            if ($billOfMaterial?->cabang_id) {
                $data['cabang_id'] = $billOfMaterial->cabang_id;
            }

            if ($billOfMaterial?->product_id) {
                $data['product_id'] = $billOfMaterial->product_id;
            }

            if ($billOfMaterial?->uom_id) {
                $data['uom_id'] = $billOfMaterial->uom_id;
            }

            $data['warehouse_id'] = null;
        }

        if (empty($data['status'])) {
            $data['status'] = 'draft';
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
