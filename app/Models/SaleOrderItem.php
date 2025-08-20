<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class SaleOrderItem extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'sale_order_items';
    protected $fillable = [
        'sale_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'warehouse_id',
        'rak_id'
    ];


    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function purchaseOrderItem()
    {
        return $this->morphMany(PurchaseOrderItem::class, 'refer_item_model');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }

    public function deliveryOrderItems()
    {
        return $this->hasMany(DeliveryOrderItem::class, 'sale_order_item_id');
    }

    public function getRemainingQuantityAttribute()
    {
        $deliveredQuantity = $this->deliveryOrderItems()
            ->whereHas('deliveryOrder', function ($query) {
                $query->whereNotIn('status', ['cancelled', 'rejected']);
            })
            ->sum('quantity');
        
        return $this->quantity - $deliveredQuantity;
    }

    public function getDeliveredQuantityAttribute()
    {
        return $this->deliveryOrderItems()
            ->whereHas('deliveryOrder', function ($query) {
                $query->whereNotIn('status', ['cancelled', 'rejected']);
            })
            ->sum('quantity');
    }
}
