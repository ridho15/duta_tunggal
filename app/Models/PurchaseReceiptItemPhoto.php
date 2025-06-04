<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceiptItemPhoto extends Model
{
    use SoftDeletes;
    protected $table = 'purchase_receipt_item_photos';
    protected $fillable = [
        'purchase_receipt_item_id',
        'photo_url'
    ];

    public function purchaseReceiptItem()
    {
        return $this->belongsTo(PurchaseReceiptItem::class, 'purchase_receipt_item_id')->withDefault();
    }
}
