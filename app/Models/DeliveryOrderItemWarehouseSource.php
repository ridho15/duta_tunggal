<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrderItemWarehouseSource extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $table = 'delivery_order_item_warehouse_sources';

    protected $fillable = [
        'delivery_order_item_id',
        'warehouse_id',
        'rak_id',
        'quantity',
    ];

    public function deliveryOrderItem()
    {
        return $this->belongsTo(DeliveryOrderItem::class, 'delivery_order_item_id')->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }
}
