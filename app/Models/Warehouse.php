<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'warehouses';
    protected $fillable = [
        'name',
        'location'
    ];

    public function rak()
    {
        return $this->hasMany(Rak::class, 'warehouse_id');
    }

    public function stockMovement()
    {
        return $this->hasMany(StockMovement::class, 'warehouse_id');
    }

    protected static function booted()
    {
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
