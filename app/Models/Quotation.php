<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'quotations';
    protected $fillable = [
        'quotation_number',
        'customer_id',
        'date',
        'valid_until',
        'total_amount',
        'status_payment',
        'po_file_path',
        'notes',
        'status',
        'created_by',
        'request_approve_by',
        'request_approve_at',
        'reject_by',
        'reject_at',
        'approve_by',
        'approve_at'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function quotationItem()
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function requestApproveBy()
    {
        return $this->belongsTo(User::class, 'request_approve_by')->withDefault();
    }

    public function rejectBy()
    {
        return $this->belongsTo(User::class, 'reject_by')->withDefault();
    }

    public function approveBy()
    {
        return $this->belongsTo(User::class, 'approve_by')->withDefault();
    }
}
