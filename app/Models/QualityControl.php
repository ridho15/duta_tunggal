<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityControl extends Model
{
    use SoftDeletes;
    protected $table = 'quality_controls';
    protected $fillable = [
        'purchase_receipt_item_id',
        'inspected_by',
        'passed_quantity',
        'rejected_quantity',
        'notes',
        'status',  // send to stock / send to delivery order
        'warehouse_id',
        'reason_reject',
        'product_id',
        'date_send_stock',
        'date_create_delivery_order',
        'rak_id'
    ];

    protected $appends = [
        'status_formatted'
    ];

    public function getStatusFormattedAttribute()
    {
        if ($this->status == 1 || $this->status == true) {
            return 'Sudah diproses';
        } else {
            return 'Belum diproses';
        }
    }

    public function purchaseReceiptItem()
    {
        return $this->belongsTo(PurchaseReceiptItem::class, 'purchase_receipt_item_id')->withDefault();
    }

    public function inspectedBy()
    {
        return $this->belongsTo(User::class, 'inspected_by')->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }
}
