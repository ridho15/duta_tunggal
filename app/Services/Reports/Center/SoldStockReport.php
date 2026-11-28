<?php

namespace App\Services\Reports\Center;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Stok Barang Terjual: barang yang keluar karena penjualan (mutasi stok tipe "sales", saat DO dikirim)
 * pada periode, per gudang, berdampingan dengan stok terakhir dan stok minimum gudang itu.
 */
class SoldStockReport
{
    /** Gudang yang boleh dilihat user (dibatasi cabang bila user bukan "semua cabang"). */
    public function warehouseIds(array $filters, ?User $user): ?array
    {
        $restricted = $user && ! in_array('all', (array) ($user->manage_type ?? []), true) && $user->cabang_id;
        if (! $restricted && empty($filters['warehouse_id']) && empty($filters['cabang_id'])) {
            return null;
        }
        $q = DB::table('warehouses')->whereNull('deleted_at');
        if ($restricted) {
            $q->where('cabang_id', $user->cabang_id);
        }
        if (! empty($filters['cabang_id'])) {
            $q->where('cabang_id', $filters['cabang_id']);
        }
        if (! empty($filters['warehouse_id'])) {
            $q->where('id', $filters['warehouse_id']);
        }

        return $q->pluck('id')->all();
    }

    /** Agregat qty terjual per produk (urut terbanyak), dasar paginasi. */
    public function productQuery(array $filters, ?User $user): Builder
    {
        $warehouses = $this->warehouseIds($filters, $user);

        return DB::table('stock_movements as sm')
            ->join('products as p', 'p.id', '=', 'sm.product_id')
            ->whereNull('sm.deleted_at')
            ->where('sm.type', 'sales')
            ->whereDate('sm.date', '>=', $filters['date_from'])
            ->whereDate('sm.date', '<=', $filters['date_to'])
            ->when($warehouses !== null, fn ($q) => $q->whereIn('sm.warehouse_id', $warehouses ?: [0]))
            ->when(trim((string) ($filters['q'] ?? '')), fn ($q, $t) => $q->where(fn ($w) => $w->where('p.name', 'like', "%{$t}%")->orWhere('p.sku', 'like', "%{$t}%")))
            ->groupBy('sm.product_id', 'p.name', 'p.sku')
            ->selectRaw('sm.product_id, p.name, p.sku, SUM(ABS(sm.quantity)) AS sold')
            ->orderByDesc('sold')->orderBy('p.sku');
    }

    /** @return array{products: int, qty: float, below_min: int} */
    public function summary(array $filters, ?User $user): array
    {
        $agg = DB::query()->fromSub($this->productQuery($filters, $user)->reorder(), 't')
            ->selectRaw('COUNT(*) AS products, COALESCE(SUM(sold),0) AS qty')->first();
        $productIds = DB::query()->fromSub($this->productQuery($filters, $user)->reorder(), 't')->pluck('product_id');
        $warehouses = $this->warehouseIds($filters, $user);
        $belowMin = $productIds->isEmpty() ? 0 : DB::table('inventory_stocks')->whereNull('deleted_at')
            ->whereIn('product_id', $productIds)
            ->when($warehouses !== null, fn ($q) => $q->whereIn('warehouse_id', $warehouses ?: [0]))
            ->where('qty_min', '>', 0)->whereColumn('qty_available', '<=', 'qty_min')
            ->distinct()->count('product_id');

        return ['products' => (int) ($agg->products ?? 0), 'qty' => (float) ($agg->qty ?? 0), 'below_min' => $belowMin];
    }

    /**
     * Rincian per gudang untuk produk di halaman ini: terjual, stok terakhir, stok minimum.
     *
     * @param  Collection<int, object>  $products
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $products, array $filters, ?User $user): array
    {
        if ($products->isEmpty()) {
            return [];
        }
        $ids = $products->pluck('product_id');
        $warehouses = $this->warehouseIds($filters, $user);

        $sold = DB::table('stock_movements')->whereNull('deleted_at')->where('type', 'sales')
            ->whereDate('date', '>=', $filters['date_from'])->whereDate('date', '<=', $filters['date_to'])
            ->whereIn('product_id', $ids)
            ->when($warehouses !== null, fn ($q) => $q->whereIn('warehouse_id', $warehouses ?: [0]))
            ->groupBy('product_id', 'warehouse_id')->selectRaw('product_id, warehouse_id, SUM(ABS(quantity)) AS qty')->get()
            ->groupBy('product_id');
        $stock = DB::table('inventory_stocks')->whereNull('deleted_at')->whereIn('product_id', $ids)
            ->get(['product_id', 'warehouse_id', 'qty_available', 'qty_min'])
            ->keyBy(fn ($r) => $r->product_id . ':' . $r->warehouse_id);
        $names = DB::table('warehouses')->pluck('name', 'id');
        $codes = DB::table('warehouses')->pluck('kode', 'id');

        return $products->map(function ($p) use ($sold, $stock, $names, $codes) {
            $lines = collect($sold->get($p->product_id, []))->map(function ($s) use ($p, $stock, $names, $codes) {
                $st = $stock->get($p->product_id . ':' . $s->warehouse_id);
                $onHand = (float) ($st->qty_available ?? 0);
                $min = (float) ($st->qty_min ?? 0);

                return [
                    'warehouse' => $codes[$s->warehouse_id] ?? ($names[$s->warehouse_id] ?? '-'),
                    'warehouse_name' => $names[$s->warehouse_id] ?? '-',
                    'sold' => (float) $s->qty,
                    'stock' => $onHand,
                    'min' => $min,
                    'flag' => $onHand <= 0 ? 'empty' : ($min > 0 && $onHand <= $min ? 'low' : 'ok'),
                ];
            })->sortByDesc('sold')->values()->all();

            return ['sku' => $p->sku, 'name' => $p->name, 'sold' => (float) $p->sold, 'lines' => $lines];
        })->all();
    }
}
