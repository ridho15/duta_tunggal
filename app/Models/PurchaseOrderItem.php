<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderItem extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'purchase_order_items';
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'tipe_pajak', // Non Pajak, Inklusif, Eklusif
        'refer_item_model_id',
        'refer_item_model_type',
        'currency_id'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id')->withDefault();
    }

    protected static function booted()
    {
        static::saving(function (PurchaseOrderItem $purchaseOrderItem) {
            $purchaseOrderItem->tipe_pajak = OrderRequestItem::normalizeItemTaxType($purchaseOrderItem->tipe_pajak ?? null);
        });

        static::creating(function (PurchaseOrderItem $purchaseOrderItem) {
            if (! empty($purchaseOrderItem->refer_item_model_type) && ! empty($purchaseOrderItem->refer_item_model_id)) {
                return;
            }

            $purchaseOrder = PurchaseOrder::withoutGlobalScopes()->find($purchaseOrderItem->purchase_order_id);
            if (! $purchaseOrder || $purchaseOrder->refer_model_type !== OrderRequest::class || ! $purchaseOrder->refer_model_id) {
                return;
            }

            $orderRequest = OrderRequest::withoutGlobalScopes()->find($purchaseOrder->refer_model_id);
            if (! $orderRequest || ! $orderRequest->exists) {
                return;
            }

            $matchedItem = $orderRequest->orderRequestItem()
                ->where('product_id', $purchaseOrderItem->product_id)
                ->when($purchaseOrder->supplier_id, function ($query) use ($purchaseOrder) {
                    $query->where(function ($supplierQuery) use ($purchaseOrder) {
                        $supplierQuery->where('supplier_id', $purchaseOrder->supplier_id)
                            ->orWhereNull('supplier_id');
                    });
                })
                ->whereRaw('quantity > COALESCE(fulfilled_quantity, 0)')
                ->orderBy('id')
                ->first();

            if (! $matchedItem) {
                return;
            }

            $purchaseOrderItem->refer_item_model_type = OrderRequestItem::class;
            $purchaseOrderItem->refer_item_model_id = $matchedItem->id;
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function referItemModel()
    {
        return $this->morphTo(__FUNCTION__, 'refer_item_model_type', 'refer_item_model_id');
    }

    public function purchaseReceiptItem()
    {
        return $this->hasMany(PurchaseReceiptItem::class, 'purchase_order_item_id');
    }

    public function qualityControl()
    {
        return $this->morphOne(QualityControl::class, 'from_model');
    }

    public function qualityControls()
    {
        return $this->morphMany(QualityControl::class, 'from_model');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
    }

    /**
     * Get the remaining quantity that can still be received
     */
    public function getRemainingQuantityAttribute()
    {
        $totalAccepted = $this->purchaseReceiptItem()->sum('qty_accepted');
        return max(0, $this->quantity - $totalAccepted);
    }

    /**
     * Get the total quantity already received
     */
    public function getTotalReceivedAttribute()
    {
        return $this->purchaseReceiptItem()->sum('qty_accepted');
    }

    public function getQuantityAttribute($value)
    {
        if (is_null($value)) {
            return $value;
        }

        $float = (float) $value;

        return $float == (int) $float ? (int) $float : $float;
    }
}
