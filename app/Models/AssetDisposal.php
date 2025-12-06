<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetDisposal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_id',
        'disposal_date',
        'disposal_type',
        'sale_price',
        'book_value_at_disposal',
        'gain_loss_amount',
        'notes',
        'disposal_document',
        'approved_by',
        'approved_at',
        'status',
    ];

    protected $casts = [
        'disposal_date' => 'date',
        'sale_price' => 'decimal:2',
        'book_value_at_disposal' => 'decimal:2',
        'gain_loss_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Accessors
    public function getGainLossTypeAttribute()
    {
        if ($this->gain_loss_amount === null) {
            return null;
        }

        return $this->gain_loss_amount >= 0 ? 'gain' : 'loss';
    }

    public function getFormattedGainLossAttribute()
    {
        if ($this->gain_loss_amount === null) {
            return '-';
        }

        $type = $this->gain_loss_type;
        $amount = abs($this->gain_loss_amount);

        return $type === 'gain' ? "Gain: Rp " . number_format($amount, 0, ',', '.') : "Loss: Rp " . number_format($amount, 0, ',', '.');
    }
}
