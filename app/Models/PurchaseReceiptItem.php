<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\CssSelector\Node\FunctionNode;

class PurchaseReceiptItem extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'purchase_receipt_items';
    protected $fillable = [
        'purchase_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'qty_received',
        'qty_accepted',
        'qty_rejected',
        'reason_rejected',
        'warehouse_id',
        'is_sent',
        'rak_id', // optional
    ];

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }

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

    public function qualityControl()
    {
        return $this->hasOne(QualityControl::class, 'purchase_receipt_item_id')->withDefault();
    }
}
