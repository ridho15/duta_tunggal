<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliverySalesOrder extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'delivery_sales_orders';
    protected $fillable = [
        'delivery_order_id',
        'sales_order_id'
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id')->withDefault();
    }

    public function salesOrder()
    {
        return $this->belongsTo(SaleOrder::class, 'sales_order_id')->withDefault();
    }
}
