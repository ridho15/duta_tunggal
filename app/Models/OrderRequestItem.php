<?php

namespace App\Models;

use App\Helpers\MoneyHelper;
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
        'supplier_id',
        'product_id',
        'quantity',
        'fulfilled_quantity',
        'unit_price',
        'original_price',
        'discount',
        'tax',
        'subtotal',
        'note'
    ];

    public function orderRequest()
    {
        return $this->belongsTo(OrderRequest::class, 'order_request_id')->withDefault();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function purchaseOrderItem()
    {
        return $this->morphOne(PurchaseOrderItem::class, 'refer_item_model')->withDefault();
    }

    protected static function booted()
    {
        static::saving(function (OrderRequestItem $item) {
            $item->loadMissing('orderRequest');

            $quantity = (float) ($item->quantity ?? 0);
            $unitPrice = MoneyHelper::parse($item->unit_price ?? 0);
            $discount = (float) ($item->discount ?? 0);
            $tax = (float) ($item->tax ?? 0);
            $taxType = $item->orderRequest?->tax_type ?? 'PPN Excluded';

            if ($taxType === 'None') {
                $tax = 0;
                $item->tax = 0;
            }

            $base = $quantity * $unitPrice;
            $afterDisc = $base - ($base * ($discount / 100));

            try {
                $taxResult = \App\Services\TaxService::compute($afterDisc, $tax, $taxType);
                $item->subtotal = $taxResult['total'];
            } catch (\Throwable $e) {
                $item->subtotal = $afterDisc;
            }
        });

        // Do not sync product_supplier on OR item save.
        // Pivot synchronization is intentionally handled during OR approval / PO creation flow.
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
