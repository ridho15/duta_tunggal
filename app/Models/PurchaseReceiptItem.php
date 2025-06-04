<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use function Pest\Laravel\withDefer;

class PurchaseReceiptItem extends Model
{
    use SoftDeletes;
    protected $table = 'purchase_receipt_items';
    protected $fillable = [
        'purchase_receipt_id',
        'product_id',
        'qty_received',
        'qty_accepted',
        'qty_rejected',
        'reason_rejected',
        'photo_url',
        'warehouse_id',
        'is_sent',
    ];

    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function purchaseReceiptItemPhoto()
    {
        return $this->hasMany(PurchaseReceiptItemPhoto::class, 'purchase_receipt_item_id');
    }

    public function purchaseReceiptItemNominal()
    {
        return $this->hasMany(PurchaseReceiptItemNominal::class, 'purchase_receipt_item_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }
}
