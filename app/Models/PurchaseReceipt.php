<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceipt extends Model
{
    use SoftDeletes;
    protected $table = 'purchase_receipts';
    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'receipt_date',
        'received_by',
        'notes'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id')->withDefault();
    }

    public function purchaseReceiptItem()
    {
        return $this->hasMany(PurchaseReceiptItem::class, 'purchase_receipt_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by')->withDefault();
    }

    public function purchaseReceiptPhoto()
    {
        return $this->hasMany(PurchaseReceiptPhoto::class, 'purchase_receipt_id');
    }
}
