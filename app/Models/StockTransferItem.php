<?php

namespace App\Models;

use App\Observers\StockTransferItemObserver;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransferItem extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'stock_transfer_items';
    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'quantity',
        'from_warehouse_id', // Gudang asal
        'from_rak_id',
        'to_warehouse_id', // Gudang Tujuan
        'to_rak_id'
    ];

    protected static function boot()
    {
        parent::boot();

        // static::observe(StockTransferItemObserver::class);
    }

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id')->withDefault();
    }

    public function fromRak()
    {
        return $this->belongsTo(Rak::class, 'from_rak_id')->withDefault();
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id')->withDefault();
    }

    public function toRak()
    {
        return $this->belongsTo(Rak::class, 'to_rak_id')->withDefault();
    }

    public function stockMovement()
    {
        return $this->morphMany(StockMovement::class, 'from_model');
    }
}
