<?php

namespace App\Models;

use App\Models\Concerns\HasSignatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Permintaan Bayar non-barang: dibuat → diajukan → disetujui → dibayar (voucher bertanda tangan). */
class ExpenseRequest extends Model
{
    use HasSignatures, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draf',
        self::STATUS_SUBMITTED => 'Menunggu persetujuan',
        self::STATUS_APPROVED => 'Disetujui, menunggu dibayar',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_PAID => 'Sudah dibayar',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = [
        'number', 'request_date', 'cabang_id', 'requested_by', 'payee', 'description', 'amount', 'status',
        'approved_by', 'approved_at', 'approval_notes', 'paid_by', 'paid_at', 'payment_coa_id',
        'cash_bank_transaction_id', 'attachment_path', 'cancel_reason',
    ];

    protected $casts = [
        'request_date' => 'date', 'approved_at' => 'datetime', 'paid_at' => 'datetime', 'amount' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseRequestLine::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function paymentCoa(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_coa_id');
    }

    public function cashBankTransaction(): BelongsTo
    {
        return $this->belongsTo(CashBankTransaction::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }
}
