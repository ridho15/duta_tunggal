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
        'warna_background',
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
}
