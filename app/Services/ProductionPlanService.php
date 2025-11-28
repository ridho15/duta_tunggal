<?php

namespace App\Services;

use App\Models\ProductionPlan;
use Illuminate\Support\Str;

class ProductionPlanService
{
    public function generatePlanNumber(): string
    {
        $date = now()->format('Ymd');
        $lastPlan = ProductionPlan::where('plan_number', 'like', "PP{$date}%")
            ->orderBy('plan_number', 'desc')
            ->first();

        if ($lastPlan) {
            $lastNumber = (int) substr($lastPlan->plan_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "PP{$date}{$newNumber}";
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