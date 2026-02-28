<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductSupplierObserver
{
    /**
     * Handle the Product "updating" event.
     * This will be triggered when pivot data is updated
     */
    public function updating(Product $product): void
    {
        // This approach won't work for pivot updates
        // We'll handle this in the RelationManager actions instead
    }

    /**
     * Handle pivot attached event
     */
    public function pivotAttached(Product $product, string $relationName, array $pivotIds): void
    {
        if ($relationName === 'suppliers') {
            // Check if any of the attached suppliers should be primary
            foreach ($pivotIds as $supplierId => $pivotData) {
                if (isset($pivotData['is_primary']) && $pivotData['is_primary']) {
                    // Unset other primary suppliers for this product
                    DB::table('product_supplier')
                        ->where('product_id', $product->id)
                        ->where('supplier_id', '!=', $supplierId)
                        ->update(['is_primary' => false]);
                }
            }
        }
    }

    /**
     * Handle pivot updated event
     */
    public function pivotUpdated(Product $product, string $relationName, array $pivotIds): void
    {
        if ($relationName === 'suppliers') {
            // Check if any of the updated suppliers should be primary
            foreach ($pivotIds as $supplierId => $pivotData) {
                if (isset($pivotData['is_primary']) && $pivotData['is_primary']) {
                    // Unset other primary suppliers for this product
                    DB::table('product_supplier')
                        ->where('product_id', $product->id)
                        ->where('supplier_id', '!=', $supplierId)
                        ->update(['is_primary' => false]);
                }
            }
        }
    }
}
