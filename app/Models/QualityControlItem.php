<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityControlItem extends Model
{
    use HasFactory, SoftDeletes, LogsGlobalActivity;

    protected $table = 'quality_control_items';

    protected $fillable = [
        'quality_control_id',
        'purchase_order_item_id',
        'product_id',
        'quantity_received',
        'passed_quantity',
        'rejected_quantity',
        'failed_qc_action',
        'reason_reject',
        'rak_id',
        'status',
        'notes',
    ];

    public function qualityControl()
    {
        return $this->belongsTo(QualityControl::class, 'quality_control_id')->withDefault();
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id')->withDefault();
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
