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
        'warna_background',
    ];

    protected $casts = [
        'is_quarantine' => 'boolean',
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
            $products = \App\Models\Product::all(['id']);
            foreach ($products as $product) {
                \App\Models\InventoryStock::firstOrCreate([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ], [
                    'qty_available' => 0,
                    'qty_reserved' => 0,
                    'qty_min' => 0,
                    'rak_id' => null,
                ]);
            }
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
}
