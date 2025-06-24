<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReceiptItemNominal extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'purchase_receipt_item_nominals';
    protected $fillable = [
        'purchase_receipt_item_id',
        'currency_id',
        'nominal'
    ];

    public function purchaseReceiptItem()
    {
        return $this->belongsTo(PurchaseReceiptItem::class, 'purchase_receipt_item_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
    }
}
