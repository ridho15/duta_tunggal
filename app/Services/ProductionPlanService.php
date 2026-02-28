<?php

namespace App\Services;

use App\Models\ProductionPlan;
use Illuminate\Support\Str;

class ProductionPlanService
{
    public function generatePlanNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "PP{$date}";
        // generate random 4-digit suffix and ensure it doesn't already exist
        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = "{$prefix}{$random}";
            $exists = ProductionPlan::where('plan_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    public function getSaleOrderOptions()
    {
        return \App\Models\SaleOrder::whereIn('status', ['approved', 'confirmed'])
            ->with(['customer', 'saleOrderItem.product'])
            ->get()
            ->mapWithKeys(function ($saleOrder) {
                $label = "SO-{$saleOrder->so_number} - {$saleOrder->customer->name}";
                return [$saleOrder->id => $label];
            });
    }

    public function getBillOfMaterialOptions()
    {
        return \App\Models\BillOfMaterial::with(['product', 'cabang'])
            ->where('is_active', true) // Only active BOMs
            ->get()
            ->mapWithKeys(function ($bom) {
                $label = "{$bom->code} - {$bom->product->name} ({$bom->cabang->nama})";
                return [$bom->id => $label];
            });
    }
}