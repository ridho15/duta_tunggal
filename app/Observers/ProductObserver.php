<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ChartOfAccount;

class ProductObserver
{
    public function creating(Product $product): void
    {
        // Set default COA mappings if not already set
        if (!$product->inventory_coa_id) {
            $inventoryCoa = ChartOfAccount::where('code', '1140.10')->first();
            if ($inventoryCoa) {
                $product->inventory_coa_id = $inventoryCoa->id;
            }
        }

        if (!$product->sales_coa_id) {
            $salesCoa = ChartOfAccount::where('code', '4100.10')->first();
            if ($salesCoa) {
                $product->sales_coa_id = $salesCoa->id;
            }
        }

        if (!$product->sales_return_coa_id) {
            $salesReturnCoa = ChartOfAccount::where('code', '4120.10')->first();
            if ($salesReturnCoa) {
                $product->sales_return_coa_id = $salesReturnCoa->id;
            }
        }

        if (!$product->sales_discount_coa_id) {
            $salesDiscountCoa = ChartOfAccount::where('code', '4110.10')->first();
            if ($salesDiscountCoa) {
                $product->sales_discount_coa_id = $salesDiscountCoa->id;
            }
        }

        if (!$product->goods_delivery_coa_id) {
            $goodsDeliveryCoa = ChartOfAccount::where('code', '1140.20')->first();
            if ($goodsDeliveryCoa) {
                $product->goods_delivery_coa_id = $goodsDeliveryCoa->id;
            }
        }

        if (!$product->cogs_coa_id) {
            $cogsCoa = ChartOfAccount::where('code', '5100.10')->first();
            if ($cogsCoa) {
                $product->cogs_coa_id = $cogsCoa->id;
            }
        }

        if (!$product->purchase_return_coa_id) {
            $purchaseReturnCoa = ChartOfAccount::where('code', '5120.10')->first();
            if ($purchaseReturnCoa) {
                $product->purchase_return_coa_id = $purchaseReturnCoa->id;
            }
        }

        if (!$product->unbilled_purchase_coa_id) {
            $unbilledPurchaseCoa = ChartOfAccount::where('code', '2190.10')->first();
            if ($unbilledPurchaseCoa) {
                $product->unbilled_purchase_coa_id = $unbilledPurchaseCoa->id;
            }
        }

        if (!$product->temporary_procurement_coa_id) {
            $temporaryProcurementCoa = ChartOfAccount::where('code', '1400.01')->first();
            if ($temporaryProcurementCoa) {
                $product->temporary_procurement_coa_id = $temporaryProcurementCoa->id;
            }
        }
    }
}