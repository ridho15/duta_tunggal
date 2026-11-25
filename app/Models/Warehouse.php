<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'warehouses';
    protected $fillable = [
        'kode',
        'name',
        'cabang_id',
        'location', // alamat
        'telepon',
        'tipe',
        'status',
        'is_quarantine',
        'is_transit', // gudang transit: barang mutasi dalam perjalanan (bukan stok jual)
        'warna_background',
    ];

    protected $casts = [
        'is_quarantine' => 'boolean',
        'is_transit' => 'boolean',
    ];

    public function rak()
    {
        return $this->hasMany(Rak::class, 'warehouse_id');
    }

    public function stockMovement()
    {
        return $this->hasMany(StockMovement::class, 'warehouse_id');
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function inventoryStock()
    {
        return $this->hasMany(InventoryStock::class, 'warehouse_id');
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);

        static::created(function ($warehouse) {
            // Gudang transit (barang mutasi dalam perjalanan) tidak perlu baris stok kosong per produk: baris dibuat saat barang lewat.
            // Dulu dibuat satu per satu untuk SEMUA produk (± 5 query/produk + log aktivitas) sehingga pengiriman mutasi pertama
            // ke suatu cabang sangat lambat / tidak merespons (UAT 25 Nov 2026).
            if ($warehouse->is_transit) {
                return;
            }

            // Baris stok kosong (qty 0) dibuat massal per 1.000 produk; observer InventoryStock tidak berbuat apa pun untuk qty 0.
            $now = now();
            \App\Models\Product::query()->select('id')->orderBy('id')->chunkById(1000, function ($products) use ($warehouse, $now) {
                $existing = \App\Models\InventoryStock::withoutGlobalScopes()->where('warehouse_id', $warehouse->id)
                    ->whereIn('product_id', $products->pluck('id'))->pluck('product_id')->flip();
                $rows = $products->reject(fn ($product) => $existing->has($product->id))->map(fn ($product) => [
                    'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'rak_id' => null,
                    'qty_available' => 0, 'qty_reserved' => 0, 'qty_min' => 0, 'created_at' => $now, 'updated_at' => $now,
                ])->values()->all();
                if ($rows !== []) {
                    \App\Models\InventoryStock::insert($rows);
                }
            });
        });

        static::deleting(function ($warehouse) {
            if ($warehouse->isForceDeleting()) {
                $warehouse->rak()->forceDelete();
            } else {
                $warehouse->rak()->delete();
            }
        });

        static::restoring(function ($warehouse) {
            $warehouse->rak()->withTrashed()->restore();
        });
    }

    public function getDeletionBlockers(): array
    {
        $blockers = [];

        $hasStock = $this->inventoryStock()
            ->where(function ($q) {
                $q->where('qty_available', '>', 0)
                    ->orWhere('qty_reserved', '>', 0);
            })->exists();

        if ($hasStock) {
            $blockers[] = 'masih memiliki stok barang aktif (tersedia/terpesan)';
        }

        if ($this->stockMovement()->exists()) {
            $blockers[] = 'memiliki ' . $this->stockMovement()->count() . ' riwayat pergerakan stok';
        }

        return $blockers;
    }

    public function getCodeAttribute(): string
    {
        return (string) ($this->attributes['kode'] ?? '');
    }
}
