<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'purchase_orders';
    protected $fillable = [
        'supplier_id',
        'po_number',
        'order_date',
        'status', //'draft','approved','partially_received','completed','closed', 'request_close', 'request_approval'
        'expected_date',
        'total_amount',
        'is_asset',
        'close_reason',
        'date_approved',
        'approved_by',
        'note',
        'close_requested_by',
        'close_requested_at',
        'closed_by',
        'closed_at',
        'close_reason',
        'completed_by',
        'completed_at',
        'created_by'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withDefault();
    }

    public function purchaseOrderItem()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by')->withDefault();
    }

    public function purchaseReceipt()
    {
        return $this->hasOne(PurchaseReceipt::class, 'purchase_order_id');
    }

    public function closeRequestedBy()
    {
        return $this->belongsTo(User::class, 'close_requested_by')->withDefault();
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by')->withDefault();
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }
}
