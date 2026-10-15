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
            ->where(fn ($q) => $q->where('is_quarantine', false)->orWhereNull('is_quarantine'))   // gudang karantina/rusak bukan stok jual
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
     * Rak dengan stok bebas terbesar untuk produk×gudang (null bila stok tersimpan tanpa rak / tidak ada stok).
     * Dipakai mengisi otomatis rak sumber DO: pemotongan stok bekerja per baris (produk, gudang, rak),
     * sehingga rak yang kosong membuat pemotongan gagal walau total gudang cukup.
     */
    public function bestRakId(int $productId, int $warehouseId): ?int
    {
        $row = DB::table('inventory_stocks')
            ->whereNull('deleted_at')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->orderByRaw('(qty_available - qty_reserved) desc')
            ->first(['rak_id']);

        return $row && $row->rak_id ? (int) $row->rak_id : null;
    }

    /**
     * Baris sumber gudang untuk SATU item DO yang menampilkan SEMUA gudang×rak berstok bebas > 0 (UAT B7): user tinggal mengisi/mengubah
     * kuantitas per baris. Kuantitas awal diisi serakah: gudang pilihan/alokasi dulu (dibatasi alokasinya), lalu gudang lain berstok
     * bebas terbanyak; baris lain bernilai 0 (dibuang saat DO disimpan). Stok bebas = bebas + reservasi milik SO itu sendiri.
     *
     * @param  array<int, int>  $preferredWarehouseIds  urutan gudang pilihan (alokasi SO / gudang item)
     * @param  array<int, float>  $caps  batas kuantitas per gudang pilihan (alokasi); gudang tanpa batas = tak terbatas
     * @return array<int, array{warehouse_id: int, rak_id: int|null, quantity: float, free: float}>
     */
    public function sourceRowsForItem(int $productId, ?int $saleOrderItemId, float $quantity, ?int $cabangId, array $preferredWarehouseIds = [], array $caps = []): array
    {
        $warehouseIds = array_values(array_unique(array_merge($this->accessibleWarehouseIds($cabangId), array_map('intval', $preferredWarehouseIds))));
        if ($warehouseIds === []) {
            return [];
        }

        $stockRows = DB::table('inventory_stocks')
            ->whereNull('deleted_at')->where('product_id', $productId)->whereIn('warehouse_id', $warehouseIds)
            ->get(['warehouse_id', 'rak_id', 'qty_available', 'qty_reserved']);

        $perWarehouse = [];
        foreach ($stockRows as $row) {
            $free = (float) $row->qty_available - (float) $row->qty_reserved;
            $perWarehouse[(int) $row->warehouse_id][] = ['rak_id' => $row->rak_id ? (int) $row->rak_id : null, 'free' => $free];
        }

        $saleOrderId = $saleOrderItemId ? DB::table('sale_order_items')->where('id', $saleOrderItemId)->value('sale_order_id') : null;
        $rows = [];
        foreach ($perWarehouse as $warehouseId => $raks) {
            usort($raks, fn ($a, $b) => $b['free'] <=> $a['free']);
            $own = $saleOrderId
                ? (float) DB::table('stock_reservations')->whereNull('material_issue_id')->where('sale_order_id', $saleOrderId)
                    ->where('product_id', $productId)->where('warehouse_id', $warehouseId)->sum('quantity')
                : 0.0;
            foreach ($raks as $i => $rak) {
                $free = max(0.0, $rak['free'] + ($i === 0 ? $own : 0.0));
                if ($free > self::EPS) {
                    $rows[] = ['warehouse_id' => (int) $warehouseId, 'rak_id' => $rak['rak_id'], 'quantity' => 0.0, 'free' => $free];
                }
            }
        }

        if ($rows === []) {
            return [];
        }

        $rank = array_flip(array_map('intval', $preferredWarehouseIds));
        $warehouseFree = [];
        foreach ($rows as $r) {
            $warehouseFree[$r['warehouse_id']] = ($warehouseFree[$r['warehouse_id']] ?? 0.0) + $r['free'];
        }
        usort($rows, function ($a, $b) use ($rank, $warehouseFree) {
            $ra = $rank[$a['warehouse_id']] ?? PHP_INT_MAX;
            $rb = $rank[$b['warehouse_id']] ?? PHP_INT_MAX;

            return [$ra, $warehouseFree[$b['warehouse_id']], $a['warehouse_id'], $b['free']] <=> [$rb, $warehouseFree[$a['warehouse_id']], $b['warehouse_id'], $a['free']];
        });

        $remaining = max(0.0, $quantity);
        $capLeft = array_map('floatval', $caps);
        foreach ($rows as &$r) {
            if ($remaining <= self::EPS) {
                break;
            }
            $limit = array_key_exists($r['warehouse_id'], $capLeft) ? $capLeft[$r['warehouse_id']] : INF;
            $take = min($remaining, $r['free'], $limit);
            if ($take > self::EPS) {
                $r['quantity'] = $take;
                $remaining -= $take;
                if (array_key_exists($r['warehouse_id'], $capLeft)) {
                    $capLeft[$r['warehouse_id']] -= $take;
                }
            }
        }
        unset($r);

        // Pass 2: gudang pilihan yang sudah habis dibatasi alokasinya — sisa tetap dicari di baris mana pun yang masih berstok.
        foreach ($rows as &$r) {
            if ($remaining <= self::EPS) {
                break;
            }
            $room = $r['free'] - $r['quantity'];
            if ($room > self::EPS) {
                $take = min($remaining, $room);
                $r['quantity'] += $take;
                $remaining -= $take;
            }
        }
        unset($r);

        if ($remaining > self::EPS) {
            // Stok total tidak cukup: bebankan sisanya ke baris pertama agar validasi melaporkan kekurangan stok dengan jelas.
            $rows[0]['quantity'] += $remaining;
        }

        return $rows;
    }

    /**
     * Menyesuaikan usulan sumber gudang DO dengan stok bebas yang nyata: tiap sumber dibatasi stok bebasnya
     * (+ reservasi milik SO), kekurangannya dialihkan ke gudang lain yang masih punya stok (terbanyak dulu), dan rak
     * kosong diisi rak berstok terbanyak. Total kuantitas tetap = $quantity bila stok mencukupi; bila stok tidak cukup,
     * sisanya dibebankan ke sumber pertama agar validasi tetap menampilkan pesan kekurangan stok yang jelas.
     *
     * @param  array<int, array{warehouse_id: int|string, quantity: float|int|string, rak_id?: int|string|null}>  $sources
     * @return array<int, array{warehouse_id: int, quantity: float, rak_id: int|null}>
     */
    public function fitSourcesToStock(array $sources, int $productId, ?int $saleOrderItemId, float $quantity, ?int $cabangId): array
    {
        $fitted = [];
        $remaining = $quantity;

        foreach ($sources as $source) {
            $warehouseId = (int) ($source['warehouse_id'] ?? 0);
            if (! $warehouseId || $remaining <= self::EPS) {
                continue;
            }
            $free = max(0.0, $this->freeForSaleOrderItem($productId, $warehouseId, $saleOrderItemId));
            $take = min((float) ($source['quantity'] ?? 0), $remaining, $free);
            if ($take > self::EPS) {
                $fitted[$warehouseId] = ['warehouse_id' => $warehouseId, 'quantity' => $take, 'rak_id' => ! empty($source['rak_id']) ? (int) $source['rak_id'] : null];
                $remaining -= $take;
            }
        }

        if ($remaining > self::EPS) {
            $candidates = collect($this->accessibleWarehouseIds($cabangId))
                ->reject(fn ($id) => isset($fitted[$id]))
                ->map(fn ($id) => ['id' => $id, 'free' => $this->freeForSaleOrderItem($productId, (int) $id, $saleOrderItemId)])
                ->filter(fn ($row) => $row['free'] > self::EPS)
                ->sortByDesc('free');

            foreach ($candidates as $row) {
                if ($remaining <= self::EPS) {
                    break;
                }
                $take = min($remaining, $row['free']);
                $fitted[$row['id']] = ['warehouse_id' => (int) $row['id'], 'quantity' => $take, 'rak_id' => null];
                $remaining -= $take;
            }
        }

        if ($remaining > self::EPS) {
            // Stok total tidak cukup: bebankan sisanya ke sumber pertama (atau sumber awal) supaya total = qty dan validasi melapor.
            $firstKey = array_key_first($fitted) ?? (int) ($sources[0]['warehouse_id'] ?? 0);
            if ($firstKey) {
                $fitted[$firstKey] ??= ['warehouse_id' => (int) $firstKey, 'quantity' => 0.0, 'rak_id' => null];
                $fitted[$firstKey]['quantity'] += $remaining;
            }
        }

        foreach ($fitted as &$row) {
            $row['rak_id'] ??= $this->bestRakId($productId, $row['warehouse_id']);
        }
        unset($row);

        return array_values($fitted);
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

    /**
     * Seperti freeForSaleOrderItem, ditambah stok yang SUDAH dipotong oleh item DO ini pada produk×gudang (opsional per rak).
     * Sejak Tahap 3 stok terpotong saat DO dibuat, jadi saat DO diedit (mis. checker mengurangi qty) kuantitas lamanya sudah tidak
     * ada di stok bebas; tanpa penambahan ini validasi menolak pengurangan qty dengan "stok tidak mencukupi".
     */
    public function freeForDeliveryOrderSource(int $productId, int $warehouseId, ?int $saleOrderItemId, ?int $rakId, ?int $deliveryOrderItemId): float
    {
        $free = $this->freeForSaleOrderItem($productId, $warehouseId, $saleOrderItemId, $rakId);

        if (! $deliveryOrderItemId) {
            return $free;
        }

        $base = DB::table('stock_movements')
            ->where('from_model_type', \App\Models\DeliveryOrderItem::class)
            ->where('from_model_id', $deliveryOrderItemId)
            ->where('warehouse_id', $warehouseId)
            ->when($rakId, fn ($q) => $q->where('rak_id', $rakId));

        $shipped = (float) (clone $base)->where('type', 'sales')->sum('quantity');
        $returned = (float) (clone $base)->where('type', 'adjustment_in')->sum('quantity');

        return $free + max(0.0, $shipped - $returned);
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
