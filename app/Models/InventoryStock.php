<?php

namespace App\Models;

use App\Observers\InventoryStockObserver;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryStock extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'inventory_stocks';
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'qty_available',
        'qty_reserved',
        'qty_min',
        'rak_id', // nullable,
    ];

    protected static function boot()
    {
        parent::boot();

        static::observe(InventoryStockObserver::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }

    /**
     * Get the qty_on_hand attribute (available - reserved).
     */
    public function getQtyOnHandAttribute()
    {
        return $this->qty_available - $this->qty_reserved;
    }
}
