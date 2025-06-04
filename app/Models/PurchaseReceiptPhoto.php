<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceiptPhoto extends Model
{
    use SoftDeletes;
    protected $table = 'purchase_receipt_photos';
    protected $fillable = [
        'purchase_receipt_id',
        'photo_url'
    ];

    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id')->withDefault();
    }
}
