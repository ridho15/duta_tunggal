<?php

namespace App\Services;

class ReturnProductService
{
    public function updateQuantityFromModel($returnProduct)
    {
        foreach ($returnProduct->returnProductItem as $returnProductItem) {
            $defaultQuantity = $returnProductItem->fromItemModel->quantity;
            $returnProductItem->fromItemModel()->update([
                'quantity' => $defaultQuantity - $returnProductItem->quantity
            ]);
        }
        $returnProduct->update([
            'status' => 'approved'
        ]);

        return $returnProduct;
    }
}
