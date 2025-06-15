<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderItem extends Model
{
    use SoftDeletes;
    protected $table = 'purchase_order_items';
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'opsi_harga', // default, negotiated, promo
        'refer_item_model_id',
        'refer_item_model_type'
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
}
