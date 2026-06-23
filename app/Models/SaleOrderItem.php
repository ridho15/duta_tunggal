<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use App\Models\TaxSetting;
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
        'delivered_quantity',
        'unit_price',
        'discount',
        'tax',
        'tipe_pajak',
        'currency_id',
        'warehouse_id',
        'rak_id',
    ];

    protected static function normalizeItemTaxType(?string $itemTaxType): string
    {
        $normalized = strtolower(trim((string) $itemTaxType));

        return match ($normalized) {
            'none', 'non pajak', 'non-pajak', 'nonpajak' => 'none',
            'inklusif', 'inclusive', 'included', 'ppn included', 'ppn-included' => 'inklusif',
            'eksklusif', 'eklusif', 'exclusive', 'ppn excluded', 'ppn_excluded' => 'eklusif',
            default => 'eklusif',
        };
    }


    public function saleOrder()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
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

    public function warehouseAllocations()
    {
        return $this->hasMany(SaleOrderItemWarehouseAllocation::class, 'sale_order_item_id');
    }

    public function getRemainingQuantityAttribute()
    {
        return $this->quantity - $this->delivered_quantity;
    }

    protected static function booted(): void
    {
        static::saving(function (SaleOrderItem $item): void {
            $itemTaxType = static::normalizeItemTaxType($item->tipe_pajak ?? null);
            $item->tipe_pajak = $itemTaxType;

            // Respect explicit tax passed in when present; otherwise fallback
            // to configuration or 'none' handling.
            if (isset($item->tax) && $item->tax !== null && $item->tax !== '') {
                $item->tax = (float) $item->tax;
            } else {
                $item->tax = $itemTaxType === 'none' ? 0 : TaxSetting::activeRate('PPN');
            }
        });
    }
}
