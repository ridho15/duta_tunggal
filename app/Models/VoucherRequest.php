<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class VoucherRequest extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'voucher_number',
        'voucher_date',
        'amount',
        'related_party',
        'description',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'requested_to_owner_at',
        'requested_to_owner_by',
        'approval_notes',
        'cash_bank_transaction_id',
        'cabang_id',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'amount' => 'float',
        'approved_at' => 'datetime',
        'requested_to_owner_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }

    /**
     * Activity Log Configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['voucher_number', 'voucher_date', 'amount', 'status', 'related_party'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Relationships
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_to_owner_by');
    }

    public function cashBankTransaction(): BelongsTo
    {
        return $this->belongsTo(CashBankTransaction::class, 'cash_bank_transaction_id');
    }

    public function cashBankTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CashBankTransaction::class, 'voucher_request_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    /**
     * Scopes
     */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', 'draft');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', 'approved');
    }

    public function scopeRejected(Builder $query): void
    {
        $query->where('status', 'rejected');
    }

    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', 'cancelled');
    }

    /**
     * Helper Methods
     */
    public function canBeSubmitted(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeRejected(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'pending']);
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Status Badge Colors untuk Filament
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'secondary',
            default => 'gray',
        };
    }

    /**
     * Status Label
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'draft' => 'Draft',
            'pending' => 'Menunggu Persetujuan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get total amount used from this voucher across all transactions
     */
    public function getTotalAmountUsed(): float
    {
        return $this->cashBankTransactions()->sum('voucher_amount_used') ?? 0;
    }

    /**
     * Get remaining voucher amount
     */
    public function getRemainingAmount(): float
    {
        return $this->amount - $this->getTotalAmountUsed();
    }

    /**
     * Check if voucher can still be used (has remaining amount)
     */
    public function canBeUsed(): bool
    {
        return $this->isApproved() && $this->getRemainingAmount() > 0;
    }

    /**
     * Check if voucher is fully used
     */
    public function isFullyUsed(): bool
    {
        return $this->getRemainingAmount() <= 0;
    }
}