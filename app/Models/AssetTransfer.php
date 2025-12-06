<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetTransfer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_id',
        'from_cabang_id',
        'to_cabang_id',
        'transfer_date',
        'reason',
        'transfer_document',
        'requested_by',
        'approved_by',
        'approved_at',
        'status',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromCabang()
    {
        return $this->belongsTo(Cabang::class, 'from_cabang_id');
    }

    public function toCabang()
    {
        return $this->belongsTo(Cabang::class, 'to_cabang_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // Accessors
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'warning',
            'approved' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }
}
