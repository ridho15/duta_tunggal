<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Quotation extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_REQUEST_APPROVE = 'request_approve';
    public const STATUS_APPROVE = 'approve';
    public const STATUS_REJECT = 'reject';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Hanya Draft dan Ditolak yang boleh diubah/dihapus. Quotation yang sedang menunggu
     * persetujuan, sudah disetujui, atau kedaluwarsa TERKUNCI (revisi lewat versi baru).
     */
    public const EDITABLE_STATUSES = [self::STATUS_DRAFT, self::STATUS_REJECT];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_REQUEST_APPROVE => 'Menunggu Persetujuan',
        self::STATUS_APPROVE => 'Disetujui',
        self::STATUS_REJECT => 'Ditolak',
        self::STATUS_EXPIRED => 'Kedaluwarsa',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    /**
     * Sudah lewat masa berlaku: ditandai expired oleh job harian, ATAU masih "approve" tetapi
     * valid_until sudah lewat (job belum berjalan). valid_until yang jatuh HARI INI masih berlaku.
     */
    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->status === self::STATUS_APPROVE
            && $this->valid_until !== null
            && $this->valid_until->copy()->startOfDay()->lt(now()->startOfDay());
    }

    /**
     * Alasan quotation TIDAK boleh dijadikan Sales Order (null = boleh).
     */
    public function unusableReasonForSaleOrder(): ?string
    {
        $number = $this->quotation_number;

        if ($this->status === self::STATUS_EXPIRED || $this->isExpired()) {
            $until = $this->valid_until?->format('d/m/Y');

            return "Quotation {$number} sudah kedaluwarsa" . ($until ? " (berlaku sampai {$until})" : '') . '. Buat revisi untuk memperbarui penawaran.';
        }

        if ($this->status !== self::STATUS_APPROVE) {
            return "Quotation {$number} berstatus \"" . (self::STATUS_LABELS[$this->status] ?? $this->status) . '" sehingga belum dapat dijadikan Sales Order.';
        }

        if ($this->superseded_at !== null) {
            return "Quotation {$number} sudah digantikan oleh revisi yang lebih baru. Gunakan versi terbaru.";
        }

        return null;
    }

    /** Quotation yang boleh dijadikan SO: Approved, belum kedaluwarsa, belum digantikan revisi. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVE)
            ->whereNull('superseded_at')
            ->where(function (Builder $q) {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString());
            });
    }

    /** Approved yang valid_until-nya sudah lewat (kandidat job "quotations:expire"). */
    public function scopeOverdueApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVE)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', now()->toDateString());
    }

    public function revisionOf()
    {
        return $this->belongsTo(Quotation::class, 'revision_of_id')->withDefault();
    }

    public function revisions()
    {
        return $this->hasMany(Quotation::class, 'revision_of_id');
    }
    protected $table = 'quotations';
    protected $casts = [
        'date' => 'datetime',
        'valid_until' => 'datetime',
        'request_approve_at' => 'datetime',
        'reject_at' => 'datetime',
        'approve_at' => 'datetime',
        'superseded_at' => 'datetime',
        'expired_at' => 'datetime',
        'exchange_rate' => 'decimal:8',
    ];

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'date',
        'valid_until',
        'currency_id',
        'exchange_rate',
        'tempo_pembayaran',
        'shipped_to',
        'total_amount',
        'status_payment',
        'po_file_path',
        'notes',
        'status', // 'draft','request_approve','approve','reject','expired'
        'created_by',
        'request_approve_by',
        'request_approve_at',
        'reject_by',
        'reject_at',
        'approve_by',
        'approve_at',
        'cabang_id',
        'revision_of_id',
        'revision_no',
        'superseded_at',
        'expired_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
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

            // Pembuat selalu tercatat, apa pun jalur pembuatannya (Filament, API/React, seeder, impor).
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        // Revisi yang DISETUJUI menggantikan versi sebelumnya (tidak dapat lagi dijadikan SO).
        static::updated(function (Quotation $quotation) {
            if ($quotation->wasChanged('status')
                && $quotation->status === self::STATUS_APPROVE
                && $quotation->revision_of_id
            ) {
                static::withoutGlobalScopes()
                    ->whereKey($quotation->revision_of_id)
                    ->whereNull('superseded_at')
                    ->update(['superseded_at' => now()]);
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
