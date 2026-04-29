<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProductSupplierSyncService
{
    /**
     * Upsert supplier price for a product without creating duplicate pivot rows.
     */
    public function syncSupplierProductPrice(?int $productId, ?int $supplierId, mixed $price): void
    {
        if (! $productId || ! $supplierId) {
            return;
        }

        $normalizedPrice = (float) ($price ?? 0);

        $existing = DB::table('product_supplier')
            ->where('product_id', $productId)
            ->where('supplier_id', $supplierId)
            ->first(['id']);

        if ($existing) {
            DB::table('product_supplier')
                ->where('id', $existing->id)
                ->update([
                    'supplier_price' => $normalizedPrice,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('product_supplier')->insert([
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'supplier_price' => $normalizedPrice,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
