<?php

namespace App\Services;

use App\Models\ReturnProduct;

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

    public function createReturnProduct($fromModel, $data)
    {
        return $fromModel->returnProduct()->create($data);
    }

    public function generateReturnNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = ReturnProduct::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->return_number, -4));
            $number = $lastNumber + 1;
        }

        return 'RN-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
