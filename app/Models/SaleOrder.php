<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleOrder extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'sale_orders';
    protected $fillable = [
        'customer_id',
        'so_number',
        'order_date',
        'status', // draft, request_approve, request_close, approved, closed, completed, confirmed, received, canceled, 'reject
        'delivery_date',
        'total_amount',
        'request_approve_by',
        'request_approve_at',
        'request_close_by',
        'request_close_at',
        'approve_by',
        'approve_at',
        'close_by',
        'close_at',
        'completed_at',
        'shipped_to',
        'reject_by',
        'reject_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function saleOrderItem()
    {
        return $this->hasMany(SaleOrderItem::class, 'sale_order_id');
    }

    public function requestApproveBy()
    {
        return $this->belongsTo(User::class, 'request_approve_by')->withDefault();
    }

    public function requestCloseBy()
    {
        return $this->belongsTo(User::class, 'request_close_by')->withDefault();
    }

    public function approveBy()
    {
        return $this->belongsTo(User::class, 'approve_by')->withDefault();
    }

    public function closeBy()
    {
        return $this->belongsTo(User::class, 'close_by')->withDefault();
    }

    public function rejectBy()
    {
        return $this->belongsTo(User::class, 'reject_by')->withDefault();
    }
}
