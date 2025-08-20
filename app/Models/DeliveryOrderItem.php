<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrderItem extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'delivery_order_items';
    protected $fillable = [
        'delivery_order_id',
        'purchase_receipt_item_id',
        'sale_order_item_id',
        'product_id',
        'quantity',
        'reason'
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function purchaseReceiptItem()
    {
        return $this->belongsTo(PurchaseReceiptItem::class, 'purchase_receipt_item_id')->withDefault();
    }

    public function saleOrderItem()
    {
        return $this->belongsTo(SaleOrderItem::class, 'sale_order_item_id')->withDefault();
    }

    protected static function booted()
    {
        static::saving(function ($deliveryOrderItem) {
            // Validate quantity doesn't exceed remaining quantity from sales order
            if ($deliveryOrderItem->sale_order_item_id) {
                $saleOrderItem = $deliveryOrderItem->saleOrderItem;
                $currentDeliveredQty = $saleOrderItem->deliveryOrderItems()
                    ->where('id', '!=', $deliveryOrderItem->id) // Exclude current item if updating
                    ->whereHas('deliveryOrder', function ($query) {
                        $query->whereNotIn('status', ['cancelled', 'rejected']);
                    })
                    ->sum('quantity');
                
                $remainingQty = $saleOrderItem->quantity - $currentDeliveredQty;
                
                if ($deliveryOrderItem->quantity > $remainingQty) {
                    throw new \Exception("Quantity ({$deliveryOrderItem->quantity}) melebihi sisa quantity yang tersedia ({$remainingQty}) untuk sales order item ini.");
                }
            }
        });
    }
}
