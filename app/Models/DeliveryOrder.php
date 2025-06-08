<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrder extends Model
{
    use SoftDeletes;
    protected $table = 'delivery_orders';
    protected $fillable = [
        'delivery_date',
        'driver_id',
        'vehicle_id',
        'status', // draft, sent,received_by, supplier
        'notes'
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id')->withDefault();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id')->withDefault();
    }

    public function deliveryOrderItem()
    {
        return $this->belongsTo(DeliveryOrderItem::class, 'delivery_order_id');
    }

    public function salesOrders()
    {
        return $this->belongsToMany(SaleOrder::class, 'delivery_sales_orders', 'delivery_order_id', 'sales_order_id');
    }

    public function suratJalan()
    {
        return $this->hasOne(SuratJalan::class, 'delivery_order_id')->withDefault();
    }

    public function log()
    {
        return $this->hasMany(DeliveryOrderLog::class, 'delivery_order_id');
    }
}
