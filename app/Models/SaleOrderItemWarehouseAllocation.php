<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleOrderItemWarehouseAllocation extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $table = 'sale_order_item_warehouse_allocations';

    protected $fillable = [
        'sale_order_item_id',
        'warehouse_id',
        'quantity',
    ];

    public function saleOrderItem()
    {
        return $this->belongsTo(SaleOrderItem::class, 'sale_order_item_id')->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }
}
