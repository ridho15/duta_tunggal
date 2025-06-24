<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Testing\Fluent\Concerns\Has;

class StockTransfer extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'stock_transfers';
    protected $fillable = [
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'transfer_date',
        'status' //Pending, completed, cancelled, Draft, Approved, Request, Reject
    ];

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id')->withDefault();
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id')->withDefault();
    }

    public function stockTransferItem()
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }

    protected static function booted()
    {
        static::deleting(function ($stockTransfer) {
            if ($stockTransfer->isForceDeleting()) {
                $stockTransfer->stockTransferItem()->forceDelete();
            } else {
                $stockTransfer->stockTransferItem()->delete();
            }
        });

        static::restoring(function ($stockTransfer) {
            $stockTransfer->stockTransferItem()->withTrashed()->restore();
        });
    }
}
