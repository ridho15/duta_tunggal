<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;

class ProductService
{
    public function generateSku()
    {
        $date = now()->format('Ymd');
        $prefix = 'SKU-' . $date . '-';

        // pick random suffix, ensure no collision
        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = Product::where('sku', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
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
    public function createStockMovement(
        int $product_id,
        int $warehouse_id,
        float $quantity,
        string $type,
        $date,
        ?string $notes,
        ?int $rak_id,
        $fromModel,
        ?float $value = null,
        array $meta = []
    )
    {
        $payload = [
            'product_id' => $product_id,
            'warehouse_id' => $warehouse_id,
            'quantity' => $quantity,
            'value' => $value,
            'type' => $type,
            'date' => $date,
            'notes' => $notes,
            'rak_id' => $rak_id,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        if ($fromModel) {
            return $fromModel->stockMovement()->create($payload);
        }

        return StockMovement::create($payload);
    }
}
