<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;

class ProductService
{
    public function updateHargaPerKategori($data)
    {
        $listProduct = Product::where('cabang_id', $data['cabang_id'])
            ->where('product_category_id', $data['product_category_id'])
            ->get();
        foreach ($listProduct as $product) {
            $perubahanHargaCost = $product->cost_price * ($data['persentase_perubahan'] / 100);
            $perubahanHargaSell = $product->sell_price * ($data['persentase_perubahan'] / 100);
            $perubahanBiaya = $product->biaya * ($data['persentase_perubahan'] / 100);
            if ($data['tipe_perubahan'] == 'Penambahan') {
                $product->update([
                    'cost_price' => $product->cost_price + $perubahanHargaCost,
                    'sell_price' => $product->sell_price + $perubahanHargaSell,
                    'biaya' => $product->biaya + $perubahanBiaya,
                ]);
            } elseif ($data['tipe_perubahan'] == 'Pengurangan') {
                $product->update([
                    'cost_price' => $product->cost_price - $perubahanHargaCost,
                    'sell_price' => $product->sell_price - $perubahanHargaSell,
                    'biaya' => $product->biaya - $perubahanBiaya
                ]);
            }
        }
    }
    public function updateHargaPerProduct($data)
    {
        foreach ($data['listProduct'] as $item) {
            $product = Product::where('cabang_id', $data['cabang_id'])
                ->where('id', $item['product_id'])
                ->first();
            if ($product) {
                $product->update([
                    'cost_price' => $item['cost_price'],
                    'sell_price' => $item['sell_price']
                ]);
            }
        }

        return true;
    }
    public function createStockMovement($product_id, $warehouse_id, $quantity, $type, $date, $notes, $rak_id, $fromModel)
    {
        if ($fromModel) {
            return $fromModel->stockMovement()->create([
                'product_id' => $product_id,
                'warehouse_id' => $warehouse_id,
                'quantity' => $quantity,
                'type' => $type,
                'date' => $date,
                'notes' => $notes,
                'rak_id' => $rak_id
            ]);
        }
        return StockMovement::create([
            'product_id' => $product_id,
            'warehouse_id' => $warehouse_id,
            'quantity' => $quantity,
            'type' => $type,
            'date' => $date,
            'notes' => $notes,
            'rak_id' => $rak_id
        ]);
    }
}
