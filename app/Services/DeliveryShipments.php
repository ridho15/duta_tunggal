<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Fakta stok pengiriman satu DO (T2.3): gudang sumber tiap item, kuantitas yang SUDAH keluar (gerakan `sales`) dikurangi yang sudah
 * dikembalikan (gerakan `adjustment_in` dari item DO yang sama), pemeriksaan stok fisik, dan pengembalian saat gagal-kirim (D18).
 */
class DeliveryShipments
{
    /**
     * Kuantitas item DO yang masih "di luar gudang" pada produk×gudang×rak itu.
     */
    public static function netShipped(DeliveryOrderItem $item, int $warehouseId, ?int $rakId): float
    {
        $base = StockMovement::query()
            ->where('from_model_type', DeliveryOrderItem::class)
            ->where('from_model_id', $item->id)
            ->where('warehouse_id', $warehouseId)
            ->when($rakId, fn ($q) => $q->where('rak_id', $rakId), fn ($q) => $q->whereNull('rak_id'));

        $shipped = (float) (clone $base)->where('type', 'sales')->sum('quantity');
        $returned = (float) (clone $base)->where('type', 'adjustment_in')->sum('quantity');

        return max(0.0, $shipped - $returned);
    }

    /**
     * Sumber pengiriman tiap item: gudang sumber DO; bila tidak ada, gudang DO/item.
     *
     * @return array<int, array{item: DeliveryOrderItem, warehouse_id: int, rak_id: int|null, quantity: float}>
     */
    public function sources(DeliveryOrder $deliveryOrder): array
    {
        $deliveryOrder->load('deliveryOrderItem.warehouseSources', 'deliveryOrderItem.product');

        $rows = [];
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $quantity = max(0.0, (float) ($item->quantity ?? 0));
            if ($quantity <= 0) {
                continue;
            }

            if ($item->warehouseSources->isNotEmpty()) {
                foreach ($item->warehouseSources as $source) {
                    $sourceQty = max(0.0, (float) ($source->quantity ?? 0));
                    if ($sourceQty > 0 && $source->warehouse_id) {
                        $rows[] = ['item' => $item, 'warehouse_id' => (int) $source->warehouse_id, 'rak_id' => $source->rak_id ? (int) $source->rak_id : null, 'quantity' => $sourceQty];
                    }
                }

                continue;
            }

            if ($deliveryOrder->warehouse_id) {
                $rows[] = ['item' => $item, 'warehouse_id' => (int) $deliveryOrder->warehouse_id, 'rak_id' => $item->rak_id ? (int) $item->rak_id : null, 'quantity' => $quantity];
            }
        }

        return $rows;
    }

    /**
     * Kekurangan stok FISIK bila DO dikirim sekarang (D15). Yang sudah keluar sebelumnya tidak dihitung lagi.
     *
     * @return array<int, array{product: string, warehouse: string, needed: float, physical: float}>
     */
    public function physicalShortages(DeliveryOrder $deliveryOrder): array
    {
        $needs = [];
        foreach ($this->sources($deliveryOrder) as $source) {
            $item = $source['item'];
            $need = max(0.0, $source['quantity'] - self::netShipped($item, $source['warehouse_id'], $source['rak_id']));
            if ($need > 0) {
                $needs[] = ['product_id' => (int) $item->product_id, 'warehouse_id' => $source['warehouse_id'], 'needed' => $need, 'product' => $item->product?->name];
            }
        }

        return $this->shortagesForNeeds($needs);
    }

    /**
     * Kekurangan stok FISIK (`qty_available`, bukan stok bebas) untuk daftar kebutuhan; kebutuhan produk×gudang yang sama dijumlahkan.
     *
     * @param  array<int, array{product_id: int, warehouse_id: int, needed: float, product?: string|null}>  $needs
     * @return array<int, array{product: string, warehouse: string, needed: float, physical: float}>
     */
    public function shortagesForNeeds(array $needs): array
    {
        $grouped = [];
        foreach ($needs as $need) {
            $key = $need['product_id'].'|'.$need['warehouse_id'];
            $grouped[$key] ??= ['product_id' => (int) $need['product_id'], 'warehouse_id' => (int) $need['warehouse_id'], 'needed' => 0.0, 'product' => $need['product'] ?? null];
            $grouped[$key]['needed'] += (float) $need['needed'];
        }

        $shortages = [];
        foreach ($grouped as $need) {
            $physical = (float) DB::table('inventory_stocks')->whereNull('deleted_at')
                ->where('product_id', $need['product_id'])->where('warehouse_id', $need['warehouse_id'])->sum('qty_available');

            if ($physical + 0.00001 < $need['needed']) {
                $shortages[] = [
                    'product' => (string) ($need['product'] ?: DB::table('products')->where('id', $need['product_id'])->value('name') ?: "Produk #{$need['product_id']}"),
                    'warehouse' => (string) (DB::table('warehouses')->where('id', $need['warehouse_id'])->value('name') ?? "Gudang #{$need['warehouse_id']}"),
                    'needed' => $need['needed'],
                    'physical' => $physical,
                ];
            }
        }

        return $shortages;
    }

    /** Rincian kekurangan sebagai kalimat untuk pengguna: "Produk X: butuh 12, stok fisik Gudang K01 hanya 8". */
    public static function describeShortages(array $shortages): string
    {
        return collect($shortages)
            ->map(fn ($s) => sprintf('%s: butuh %s, stok fisik %s hanya %s', $s['product'], self::fmt($s['needed']), $s['warehouse'], self::fmt($s['physical'])))
            ->implode('; ');
    }

    private static function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') ?: '0';
    }

    /**
     * Kembalikan stok fisik seluruh kuantitas yang masih di luar gudang (gagal kirim, D18) lewat gerakan masuk berjejak.
     * Mengembalikan total unit yang dikembalikan.
     */
    public function reverse(DeliveryOrder $deliveryOrder, string $reason): float
    {
        $product = app(ProductService::class);
        $total = 0.0;

        foreach ($this->sources($deliveryOrder) as $source) {
            $item = $source['item'];
            $net = self::netShipped($item, $source['warehouse_id'], $source['rak_id']);
            if ($net <= 0 || ! $item->product) {
                continue;
            }

            $product->createStockMovement(
                product_id: $item->product_id,
                warehouse_id: $source['warehouse_id'],
                quantity: $net,
                type: 'adjustment_in',
                date: now()->toDateString(),
                notes: "Pengiriman gagal DO {$deliveryOrder->do_number}: stok dikembalikan — {$reason}",
                rak_id: $source['rak_id'],
                fromModel: $item,
                value: $item->product->cost_price * $net,
                meta: ['delivery_reversal' => true, 'reason' => $reason, 'source' => 'delivery_order_transitions']
            );

            $total += $net;
        }

        return $total;
    }
}
