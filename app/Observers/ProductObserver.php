<?php

namespace App\Observers;

use App\Models\ChartOfAccount;
use App\Models\Product;

class ProductObserver
{
    public function creating(Product $product): void
    {
        $this->fillMissingCoaMappings($product);
    }

    public function updating(Product $product): void
    {
        $this->fillMissingCoaMappings($product);
    }

    protected function fillMissingCoaMappings(Product $product): void
    {
        if (! $product->inventory_coa_id) {
            $product->inventory_coa_id = Product::resolveDefaultProductCoaId(
                'inventory_coa_id',
                (bool) $product->is_manufacture,
                (bool) $product->is_raw_material,
            ) ?? $product->resolveInventoryCoaOrDefault()?->id;
        }

        if (! $product->sales_coa_id) {
            $product->sales_coa_id = Product::resolveDefaultProductCoaId('sales_coa_id');
        }

        if (! $product->sales_return_coa_id) {
            $product->sales_return_coa_id = Product::resolveDefaultProductCoaId('sales_return_coa_id');
        }

        if (! $product->sales_discount_coa_id) {
            $product->sales_discount_coa_id = Product::resolveDefaultProductCoaId('sales_discount_coa_id');
        }

        if (! $product->goods_delivery_coa_id) {
            $product->goods_delivery_coa_id = Product::resolveDefaultProductCoaId('goods_delivery_coa_id');
        }

        if (! $product->cogs_coa_id) {
            $product->cogs_coa_id = Product::resolveDefaultProductCoaId('cogs_coa_id');
        }

        if (! $product->purchase_return_coa_id) {
            $product->purchase_return_coa_id = Product::resolveDefaultProductCoaId('purchase_return_coa_id');
        }

        if (! $product->unbilled_purchase_coa_id) {
            $product->unbilled_purchase_coa_id = Product::resolveDefaultProductCoaId('unbilled_purchase_coa_id');
        }

        if (! $product->temporary_procurement_coa_id) {
            $product->temporary_procurement_coa_id = Product::resolveDefaultProductCoaId('temporary_procurement_coa_id');
        }

        if (! $product->manufacturing_labor_coa_id) {
            $product->manufacturing_labor_coa_id = Product::resolveDefaultProductCoaId('manufacturing_labor_coa_id');
        }

        if (! $product->manufacturing_overhead_coa_id) {
            $product->manufacturing_overhead_coa_id = Product::resolveDefaultProductCoaId('manufacturing_overhead_coa_id');
        }
    }
}