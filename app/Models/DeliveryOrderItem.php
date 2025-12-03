<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

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

    public function stockMovement()
    {
        return $this->morphOne(StockMovement::class, 'from_model')->withDefault();
    }

    protected static function booted()
    {
        static::created(function ($deliveryOrderItem) {
            // Update delivered_quantity when delivery order item is created
            if ($deliveryOrderItem->sale_order_item_id) {
                $saleOrderItem = $deliveryOrderItem->saleOrderItem;
                if ($saleOrderItem) {
                    // Only update if delivery order is in final status
                    $deliveryOrder = $deliveryOrderItem->deliveryOrder;
                    if ($deliveryOrder && in_array($deliveryOrder->status, ['sent', 'received', 'completed'])) {
                        $totalDelivered = $saleOrderItem->deliveryOrderItems()
                            ->whereHas('deliveryOrder', function ($query) {
                                $query->whereIn('status', ['sent', 'received', 'completed']);
                            })
                            ->sum('quantity');

                        $saleOrderItem->update([
                            'delivered_quantity' => $totalDelivered
                        ]);
                    }
                }
            }
        });

        static::saving(function ($deliveryOrderItem) {
            // Validate quantity doesn't exceed remaining quantity from sales order
            if ($deliveryOrderItem->sale_order_item_id) {
                $saleOrderItem = $deliveryOrderItem->saleOrderItem;
                $currentDeliveredQty = $saleOrderItem->deliveryOrderItems()
                    ->where('id', '!=', $deliveryOrderItem->id) // Exclude current item if updating
                    ->whereHas('deliveryOrder', function ($query) {
                        $query->whereIn('status', ['sent', 'received', 'completed']); // Only count actually delivered orders
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
