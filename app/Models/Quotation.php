<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Quotation extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'quotations';
    protected $casts = [
        'date' => 'datetime',
        'valid_until' => 'datetime',
        'request_approve_at' => 'datetime',
        'reject_at' => 'datetime',
        'approve_at' => 'datetime',
    ];

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'date',
        'valid_until',
        'tempo_pembayaran',
        'total_amount',
        'status_payment',
        'po_file_path',
        'notes',
        'status', // 'draft','request_approve','approve','reject'
        'created_by',
        'request_approve_by',
        'request_approve_at',
        'reject_by',
        'reject_at',
        'approve_by',
        'approve_at',
        'cabang_id',
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

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    protected static function booted()
    {
        // Auto-assign cabang_id when creating so new quotations are branch-scoped
        static::creating(function ($model) {
            if (empty($model->cabang_id)) {
                $model->cabang_id = Auth::user()?->cabang_id;
            }
        });

        static::deleting(function ($quotation) {
            if ($quotation->isForceDeleting()) {
                $quotation->quotationItem()->forceDelete();
            } else {
                $quotation->quotationItem()->delete();
            }
        });

        static::restoring(function ($quotation) {
            $quotation->quotationItem()->withTrashed()->restore();
        });
    }
}
