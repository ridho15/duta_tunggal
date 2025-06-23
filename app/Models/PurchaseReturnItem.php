<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturnItem extends Model
{
    use SoftDeletes;
    protected $table = 'purchase_return_items';
    protected $fillable = [
        'purchase_return_id',
        'purchase_receipt_item_id',
        'product_id',
        'qty_returned',
        'unit_price',
        'reason'
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function purchaseReceiptItem()
    {
        return $this->belongsTo(PurchaseReceiptItem::class, 'purchase_receipt_item_id')->withDefault();
    }
}
