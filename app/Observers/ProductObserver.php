<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ChartOfAccount;

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
            $product->inventory_coa_id = Product::resolveDefaultInventoryCoaId(
                (bool) $product->is_manufacture,
                (bool) $product->is_raw_material,
            ) ?? $product->resolveInventoryCoaOrDefault()?->id;
        }

        if (! $product->sales_coa_id) {
            $salesCoa = ChartOfAccount::where('code', '4100.10')->first();
            if ($salesCoa) {
                $product->sales_coa_id = $salesCoa->id;
            }
        }

        if (! $product->sales_return_coa_id) {
            $salesReturnCoa = ChartOfAccount::where('code', '4120.10')->first();
            if ($salesReturnCoa) {
                $product->sales_return_coa_id = $salesReturnCoa->id;
            }
        }

        if (! $product->sales_discount_coa_id) {
            $salesDiscountCoa = ChartOfAccount::where('code', '4110.10')->first();
            if ($salesDiscountCoa) {
                $product->sales_discount_coa_id = $salesDiscountCoa->id;
            }
        }

        if (! $product->goods_delivery_coa_id) {
            $goodsDeliveryCoa = $product->resolveGoodsDeliveryCoaOrDefault();
            if ($goodsDeliveryCoa) {
                $product->goods_delivery_coa_id = $goodsDeliveryCoa->id;
            }
        }

        if (! $product->cogs_coa_id) {
            $cogsCoa = $product->resolveCogsCoaOrDefault();
            if ($cogsCoa) {
                $product->cogs_coa_id = $cogsCoa->id;
            }
        }

        if (! $product->purchase_return_coa_id) {
            $purchaseReturnCoa = $product->resolvePurchaseReturnCoaOrDefault();
            if ($purchaseReturnCoa) {
                $product->purchase_return_coa_id = $purchaseReturnCoa->id;
            }
        }

        if (! $product->unbilled_purchase_coa_id) {
            $unbilledPurchaseCoa = $product->resolveUnbilledPurchaseCoaOrDefault();
            if ($unbilledPurchaseCoa) {
                $product->unbilled_purchase_coa_id = $unbilledPurchaseCoa->id;
            }
        }

        if (! $product->temporary_procurement_coa_id) {
            $temporaryProcurementCoa = $product->resolveTemporaryProcurementCoaOrDefault();
            if ($temporaryProcurementCoa) {
                $product->temporary_procurement_coa_id = $temporaryProcurementCoa->id;
            }
        }

        if (! $product->manufacturing_labor_coa_id) {
            $manufacturingLaborCoa = $product->resolveManufacturingLaborCoaOrDefault();
            if ($manufacturingLaborCoa) {
                $product->manufacturing_labor_coa_id = $manufacturingLaborCoa->id;
            }
        }

        if (! $product->manufacturing_overhead_coa_id) {
            $manufacturingOverheadCoa = $product->resolveManufacturingOverheadCoaOrDefault();
            if ($manufacturingOverheadCoa) {
                $product->manufacturing_overhead_coa_id = $manufacturingOverheadCoa->id;
            }
        }
    }
}