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

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
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
