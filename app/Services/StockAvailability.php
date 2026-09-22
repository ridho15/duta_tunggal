<?php

namespace App\Services;

use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stok bebas yang SADAR reservasi dan kebijakan cabang (T2.2, keputusan D3).
 *
 * Stok bebas = stok fisik − qty_reserved. Begitu SO menahan stok (D1), reservasi SO itu sendiri tidak boleh menghalangi
 * SO/DO-nya: karena itu semua pemeriksaan untuk sebuah SO memakai `free + reservasi milik SO itu` (`freeForSaleOrders`).
 *
 * Kebijakan cabang (D3) berlaku untuk keputusan OTOMATIS — item yang tidak memilih gudang: gudang cabang SO, ditambah gudang
 * cabang lain bila `Cabang.lihat_stok_cabang_lain`. Gudang yang DIPILIH pengguna pada item/alokasi/sumber DO selalu dihormati.
 */
class StockAvailability
{
    private const EPS = 0.00001;

    /** @return array<int, int> id gudang aktif yang boleh dipakai keputusan otomatis untuk $cabangId (null = semua gudang aktif) */
    public function accessibleWarehouseIds(?int $cabangId): array
    {
        $seeOthers = $cabangId
            ? (bool) DB::table('cabangs')->where('id', $cabangId)->value('lihat_stok_cabang_lain')
            : true;

        return DB::table('warehouses')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('status', 1)->orWhereNull('status'))
            ->when(! $seeOthers && $cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Stok per gudang untuk satu produk: [warehouseId => ['available', 'reserved', 'free']].
     *
     * @param  array<int, int>|null  $warehouseIds  null = semua gudang
     * @return array<int, array{available: float, reserved: float, free: float}>
     */
    public function stockByWarehouse(int $productId, ?array $warehouseIds = null, ?int $rakId = null): array
    {
        return $this->stockMap([$productId], $warehouseIds, $rakId)[$productId] ?? [];
    }

    /** Stok bebas satu produk×gudang (opsional per rak) — tanpa add-back reservasi. */
    public function freeInWarehouse(int $productId, int $warehouseId, ?int $rakId = null): float
    {
        return $this->stockByWarehouse($productId, [$warehouseId], $rakId)[$warehouseId]['free'] ?? 0.0;
    }

    /**
     * Stok bebas untuk SO/DO: bebas + reservasi yang dimiliki SO tersebut pada produk×gudang itu.
     *
     * @param  array<int, int>  $saleOrderIds
     */
    public function freeForSaleOrders(int $productId, int $warehouseId, array $saleOrderIds, ?int $rakId = null): float
    {
        $free = $this->freeInWarehouse($productId, $warehouseId, $rakId);

        if ($saleOrderIds === []) {
            return $free;
        }

        $own = (float) DB::table('stock_reservations')
            ->whereNull('material_issue_id')
            ->whereIn('sale_order_id', $saleOrderIds)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');

        return $free + $own;
    }

    /** Seperti freeForSaleOrders, tetapi SO diturunkan dari id item SO (dipakai form/validasi DO). */
    public function freeForSaleOrderItem(int $productId, int $warehouseId, ?int $saleOrderItemId, ?int $rakId = null): float
    {
        $saleOrderId = $saleOrderItemId
            ? DB::table('sale_order_items')->where('id', $saleOrderItemId)->value('sale_order_id')
            : null;

        return $this->freeForSaleOrders($productId, $warehouseId, $saleOrderId ? [(int) $saleOrderId] : [], $rakId);
    }

    /** Stok bebas satu produk untuk keputusan otomatis suatu cabang (Σ gudang yang boleh dipakai) — dipakai daftar produk API/React. */
    public function freeForCabang(int $productId, ?int $cabangId): float
    {
        $free = 0.0;
        foreach ($this->stockByWarehouse($productId, $this->accessibleWarehouseIds($cabangId)) as $row) {
            $free += $row['free'];
        }

        return $free;
    }

    /**
     * Stok bebas SEMUA produk untuk satu cabang sekaligus: [productId => free] (satu kueri; menggantikan Σ semua cabang).
     *
     * @return array<int, float>
     */
    public function freeByProductForCabang(?int $cabangId): array
    {
        $warehouseIds = $this->accessibleWarehouseIds($cabangId);
        if ($warehouseIds === []) {
            return [];
        }

        return DB::table('inventory_stocks')
            ->whereNull('deleted_at')
            ->whereIn('warehouse_id', $warehouseIds)
            ->selectRaw('product_id, SUM(qty_available - qty_reserved) as free_stock')
            ->groupBy('product_id')
            ->pluck('free_stock', 'product_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Pemeriksaan stok satu SO. Per item: {item, needed, available, shortage, warehouses}.
     *  - item dengan ALOKASI gudang: setiap alokasi diperiksa pada gudangnya (dan jumlah alokasi harus = qty item);
     *  - item dengan gudang terpilih: gudang itu (dan rak bila dipilih);
     *  - item tanpa gudang: Σ gudang yang boleh dipakai cabang SO (D3).
     *
     * @return array{items: array<int, array<string, mixed>>, has_shortage: bool, shortage_items: array<int, array<string, mixed>>}
     */
    public function check(SaleOrder $saleOrder): array
    {
        return $this->checkMany([$saleOrder])[$saleOrder->getKey()];
    }

    /**
     * Kalimat kekurangan untuk pengguna dari hasil check(): "Alat X: diminta 35, stok bebas 30 (kurang 5) di Gudang K01".
     *
     * @param  array{shortage_items: array<int, array<string, mixed>>}  $check
     * @return array<int, string>  satu kalimat per item kurang
     */
    public function describeShortages(array $check): array
    {
        $names = DB::table('warehouses')->whereIn('id', collect($check['shortage_items'])->flatMap(fn ($r) => array_keys($r['warehouses']))->unique()->all() ?: [0])
            ->pluck('name', 'id');

        return array_map(function (array $row) use ($names): string {
            $product = $row['item']->product?->name ?? 'Produk #'.$row['item']->product_id;

            if ($row['allocation_mismatch']) {
                return "{$product}: ".($row['note'] ?? 'alokasi gudang tidak sesuai qty item');
            }

            $where = collect($row['warehouses'])->keys()->map(fn ($id) => $names[$id] ?? "Gudang #{$id}")->implode(', ');

            return sprintf(
                '%s: diminta %s, stok bebas %s (kurang %s)%s',
                $product,
                $this->fmt($row['needed']),
                $this->fmt($row['available']),
                $this->fmt($row['shortage']),
                $where !== '' ? " di {$where}" : ''
            );
        }, array_values($check['shortage_items']));
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') ?: '0';
    }

    /**
     * Pemeriksaan banyak SO sekaligus: jumlah query tetap (daftar SO tanpa N+1).
     *
     * @param  iterable<SaleOrder>  $saleOrders
     * @return array<int, array{items: array<int, array<string, mixed>>, has_shortage: bool, shortage_items: array<int, array<string, mixed>>}>
     */
    public function checkMany(iterable $saleOrders): array
    {
        $orders = collect($saleOrders)->values();
        if ($orders->isEmpty()) {
            return [];
        }

        // Relasi dimuat SEKALI untuk seluruh SO (bukan per SO)
        (new \Illuminate\Database\Eloquent\Collection($orders->all()))->loadMissing('saleOrderItem.warehouseAllocations', 'saleOrderItem.product');

        $items = $orders->flatMap(fn (SaleOrder $order) => $order->saleOrderItem);
        $productIds = $items->pluck('product_id')->filter()->unique()->values()->all();

        // Semua stok yang relevan dalam SATU kueri
        $stock = $this->stockMap($productIds, null, null);

        // Semua reservasi milik SO-SO ini (untuk add-back) dalam SATU kueri
        $own = DB::table('stock_reservations')
            ->whereNull('material_issue_id')
            ->whereIn('sale_order_id', $orders->pluck('id')->all())
            ->selectRaw('sale_order_id, product_id, warehouse_id, SUM(quantity) as total')
            ->groupBy('sale_order_id', 'product_id', 'warehouse_id')
            ->get()
            ->groupBy(fn ($r) => $r->sale_order_id.'|'.$r->product_id.'|'.$r->warehouse_id)
            ->map(fn ($g) => (float) $g->sum('total'));

        $accessible = [];
        $result = [];

        foreach ($orders as $order) {
            $accessible[$order->cabang_id ?? 0] ??= $this->accessibleWarehouseIds($order->cabang_id);

            $itemResults = [];
            foreach ($order->saleOrderItem as $item) {
                $itemResults[] = $this->checkItem($order, $item, $stock, $own, $accessible[$order->cabang_id ?? 0]);
            }

            $shortages = array_values(array_filter($itemResults, fn ($r) => $r['shortage'] > self::EPS || $r['allocation_mismatch']));
            $result[$order->getKey()] = ['items' => $itemResults, 'has_shortage' => $shortages !== [], 'shortage_items' => $shortages];
        }

        return $result;
    }

    /**
     * @param  array<int, array<int, array{available: float, reserved: float, rows: array<int, array<string, float>>}>>  $stock
     * @param  Collection<string, float>  $own
     * @param  array<int, int>  $accessibleWarehouseIds
     * @return array<string, mixed>
     */
    private function checkItem(SaleOrder $order, SaleOrderItem $item, array $stock, Collection $own, array $accessibleWarehouseIds): array
    {
        $needed = (float) $item->quantity;
        $productId = (int) $item->product_id;
        $freeAt = function (int $warehouseId, ?int $rakId = null) use ($stock, $own, $order, $productId): float {
            $entry = $stock[$productId][$warehouseId] ?? null;
            $free = $entry ? ($rakId ? ($entry['rows'][$rakId]['free'] ?? 0.0) : $entry['free']) : 0.0;

            return $free + (float) ($own[$order->getKey().'|'.$productId.'|'.$warehouseId] ?? 0.0);
        };

        $allocations = $item->warehouseAllocations;
        $warehouses = [];

        if ($allocations->isNotEmpty()) {
            $allocated = (float) $allocations->sum('quantity');
            $fulfillable = 0.0;
            foreach ($allocations as $allocation) {
                $free = $freeAt((int) $allocation->warehouse_id);
                $warehouses[(int) $allocation->warehouse_id] = $free;
                // Yang dapat dipenuhi di suatu gudang dibatasi oleh alokasinya dan oleh stok bebasnya
                $fulfillable += min((float) $allocation->quantity, max(0.0, $free));
            }

            $mismatch = abs($allocated - $needed) > 0.0001;

            return $this->itemResult($item, $needed, min($needed, $fulfillable), $warehouses, $mismatch, $mismatch ? 'jumlah alokasi gudang tidak sama dengan qty item' : null);
        }

        if ($item->warehouse_id) {
            $free = $freeAt((int) $item->warehouse_id, $item->rak_id ? (int) $item->rak_id : null);

            return $this->itemResult($item, $needed, max(0.0, $free), [(int) $item->warehouse_id => $free]);
        }

        $available = 0.0;
        foreach ($accessibleWarehouseIds as $warehouseId) {
            $free = $freeAt($warehouseId);
            $warehouses[$warehouseId] = $free;
            $available += max(0.0, $free);
        }

        return $this->itemResult($item, $needed, $available, $warehouses);
    }

    /** @param  array<int, float>  $warehouses */
    private function itemResult(SaleOrderItem $item, float $needed, float $available, array $warehouses, bool $allocationMismatch = false, ?string $note = null): array
    {
        return [
            'item' => $item,
            'needed' => $needed,
            'available' => $available,
            'shortage' => max(0.0, round($needed - $available, 4)),
            'warehouses' => $warehouses,
            'allocation_mismatch' => $allocationMismatch,
            'note' => $note,
        ];
    }

    /**
     * Peta stok: [productId => [warehouseId => ['available','reserved','free','rows' => [rakId => ['free']]]]].
     *
     * @param  array<int, int>  $productIds
     * @param  array<int, int>|null  $warehouseIds
     */
    private function stockMap(array $productIds, ?array $warehouseIds, ?int $rakId): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = DB::table('inventory_stocks')
            ->whereNull('deleted_at')
            ->whereIn('product_id', $productIds)
            ->when($warehouseIds !== null, fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when($rakId, fn ($q) => $q->where('rak_id', $rakId))
            ->get(['product_id', 'warehouse_id', 'rak_id', 'qty_available', 'qty_reserved']);

        $map = [];
        foreach ($rows as $row) {
            $entry = &$map[(int) $row->product_id][(int) $row->warehouse_id];
            $entry ??= ['available' => 0.0, 'reserved' => 0.0, 'free' => 0.0, 'rows' => []];
            $free = (float) $row->qty_available - (float) $row->qty_reserved;
            $entry['available'] += (float) $row->qty_available;
            $entry['reserved'] += (float) $row->qty_reserved;
            $entry['free'] += $free;
            if ($row->rak_id) {
                $entry['rows'][(int) $row->rak_id]['free'] = ($entry['rows'][(int) $row->rak_id]['free'] ?? 0.0) + $free;
            }
            unset($entry);
        }

        return $map;
    }
}
