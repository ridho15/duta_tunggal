<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrder extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'delivery_orders';
    protected $fillable = [
        'do_number',
        'delivery_date',
        'driver_id',
        'vehicle_id',
        'status', // 'draft', 'sent', 'received', 'supplier', 'completed', 'request_approve', 'approved', 'request_close', 'closed', 'reject'
        'notes',
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
        return $this->hasMany(DeliveryOrderItem::class, 'delivery_order_id');
    }

    public function salesOrders()
    {
        return $this->belongsToMany(SaleOrder::class, 'delivery_sales_orders', 'delivery_order_id', 'sales_order_id');
    }

    public function suratJalan()
    {
        return $this->hasOne(SuratJalan::class, 'delivery_order_id')->withDefault();
    }

    public function deliverySalesOrder()
    {
        return $this->hasMany(DeliverySalesOrder::class, 'delivery_order_id');
    }

    public function log()
    {
        return $this->hasMany(DeliveryOrderLog::class, 'delivery_order_id');
    }

    protected static function booted()
    {
        static::deleting(function ($deliveryOrder) {
            if ($deliveryOrder->isForceDeleting()) {
                $deliveryOrder->deliveryOrderItem()->forceDelete();
            } else {
                $deliveryOrder->deliveryOrderItem()->delete();
            }
        });

        static::restoring(function ($deliveryOrder) {
            $deliveryOrder->deliveryOrderItem()->withTrashed()->restore();
        });
    }
}
