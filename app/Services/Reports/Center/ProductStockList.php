<?php

namespace App\Services\Reports\Center;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cetak Banyak Produk: daftar stok per produk per gudang untuk rentang kode produk, cabang, gudang, dan tipe pajak.
 * "Dengan nilai" menambah harga pokok & nilai persediaan (qty x harga pokok).
 */
class ProductStockList
{
    public function query(array $filters, ?User $user): Builder
    {
        $restricted = $user && ! in_array('all', (array) ($user->manage_type ?? []), true) && $user->cabang_id;

        return DB::table('inventory_stocks as s')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->join('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'p.uom_id')
            ->leftJoin('cabangs as c', 'c.id', '=', 'w.cabang_id')
            ->whereNull('s.deleted_at')->whereNull('p.deleted_at')->whereNull('w.deleted_at')
            ->where(fn ($q) => $q->whereNull('w.is_transit')->orWhere('w.is_transit', false))
            ->when($restricted, fn ($q) => $q->where('w.cabang_id', $user->cabang_id))
            ->when($filters['sku_from'] ?? null, fn ($q, $v) => $q->where('p.sku', '>=', $v))
            ->when($filters['sku_to'] ?? null, fn ($q, $v) => $q->where('p.sku', '<=', $v))
            ->when($filters['cabang_id'] ?? null, fn ($q, $v) => $q->where('w.cabang_id', $v))
            ->when($filters['warehouse_id'] ?? null, fn ($q, $v) => $q->where('s.warehouse_id', $v))
            ->when($filters['tipe_pajak'] ?? null, fn ($q, $v) => $q->where('p.tipe_pajak', $v))
            ->when(! ($filters['include_zero'] ?? false), fn ($q) => $q->where('s.qty_available', '!=', 0))
            ->select([
                'p.sku', 'p.name', 'p.tipe_pajak', 'u.name as uom', 'w.kode as warehouse_code', 'w.name as warehouse',
                'c.nama as branch', 's.qty_available as qty', 's.qty_reserved as reserved', 'p.cost_price',
            ])
            ->orderBy('p.sku')->orderBy('w.kode');
    }

    /** @return array{lines: int, products: int, qty: float, value: float} */
    public function summary(Builder $query): array
    {
        $agg = DB::query()->fromSub((clone $query)->reorder(), 't')
            ->selectRaw('COUNT(*) AS lines_count, COUNT(DISTINCT sku) AS products, COALESCE(SUM(qty),0) AS qty, COALESCE(SUM(qty * COALESCE(cost_price,0)),0) AS value')
            ->first();

        return [
            'lines' => (int) ($agg->lines_count ?? 0),
            'products' => (int) ($agg->products ?? 0),
            'qty' => (float) ($agg->qty ?? 0),
            'value' => round((float) ($agg->value ?? 0), 2),
        ];
    }
}
