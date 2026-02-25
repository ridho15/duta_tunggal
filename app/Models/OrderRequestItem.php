<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderRequestItem extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'order_request_items';
    protected $fillable = [
        'order_request_id',
        'product_id',
        'quantity',
        'fulfilled_quantity',
        'unit_price',
        'discount',
        'tax',
        'subtotal',
        'note'
    ];

    public function orderRequest()
    {
        return $this->belongsTo(OrderRequest::class, 'order_request_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function purchaseOrderItem()
    {
        return $this->morphOne(PurchaseOrderItem::class, 'refer_item_model')->withDefault();
    }

    /**
     * Update fulfilled quantity by adding the given amount
     */
    public function addFulfilledQuantity($quantity)
    {
        $this->fulfilled_quantity = ($this->fulfilled_quantity ?? 0) + $quantity;
        $this->save();
    }

    /**
     * Update fulfilled quantity by subtracting the given amount
     */
    public function reduceFulfilledQuantity($quantity)
    {
        $this->fulfilled_quantity = max(0, ($this->fulfilled_quantity ?? 0) - $quantity);
        $this->save();
    }

    /**
     * Get remaining quantity (not yet fulfilled)
     */
    public function getRemainingQuantityAttribute()
    {
        return max(0, $this->quantity - ($this->fulfilled_quantity ?? 0));
    }
}
