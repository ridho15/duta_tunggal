<?php

namespace App\Models;

use App\Helpers\MoneyHelper;
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
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'disposal_date' => 'date',
        'sale_price' => 'decimal:2',
        'book_value_at_disposal' => 'decimal:2',
        'gain_loss_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
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

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'voided']);
    }

    public function isVoided(): bool
    {
        return in_array($this->status, ['cancelled', 'voided']);
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
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

        return $type === 'gain' ? 'Gain: ' . MoneyHelper::rupiah($amount) : 'Loss: ' . MoneyHelper::rupiah($amount);
    }
}
