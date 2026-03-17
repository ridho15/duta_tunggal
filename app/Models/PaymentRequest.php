<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payment_requests';

    protected $fillable = [
        'request_number',
        'supplier_id',
        'cabang_id',
        'requested_by',
        'approved_by',
        'request_date',
        'payment_date',
        'total_amount',
        'selected_invoices',
        'notes',
        'approval_notes',
        'status',
        'approved_at',
        'vendor_payment_id',
    ];

    protected $casts = [
        'selected_invoices' => 'array',
        // request_date, payment_date, and approved_at handled via accessors to guard against invalid DB values like '-'
    ];

    /**
     * Accessor for approved_at — guards against invalid DB values like '-'.
     */
    public function getApprovedAtAttribute($value): ?\Illuminate\Support\Carbon
    {
        if (!$value || trim((string)$value) === '' || trim((string)$value) === '-') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Mutator for approved_at — converts invalid values to null before saving.
     */
    public function setApprovedAtAttribute(mixed $value): void
    {
        if (!$value || (is_string($value) && (trim($value) === '' || trim($value) === '-'))) {
            $this->attributes['approved_at'] = null;
        } else {
            $this->attributes['approved_at'] = $value;
        }
    }

    /**
     * Accessor for request_date — guards against invalid DB values like '-'.
     */
    public function getRequestDateAttribute($value): ?\Illuminate\Support\Carbon
    {
        if (!$value || trim((string)$value) === '' || trim((string)$value) === '-') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Mutator for request_date — converts invalid values to null before saving.
     */
    public function setRequestDateAttribute(mixed $value): void
    {
        if (!$value || (is_string($value) && (trim($value) === '' || trim($value) === '-'))) {
            $this->attributes['request_date'] = null;
        } else {
            $this->attributes['request_date'] = $value;
        }
    }

    /**
     * Accessor for payment_date — guards against invalid DB values like '-'.
     * Returns a Carbon instance or null. Bypasses the Eloquent date cast
     * to prevent DateMalformedStringException on legacy records.
     */
    public function getPaymentDateAttribute(?string $value): ?\Illuminate\Support\Carbon
    {
        if (!$value || trim($value) === '' || trim($value) === '-') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Mutator for payment_date — converts invalid values to null before saving.
     */
    public function setPaymentDateAttribute(mixed $value): void
    {
        if (!$value || (is_string($value) && (trim($value) === '' || trim($value) === '-'))) {
            $this->attributes['payment_date'] = null;
        } else {
            $this->attributes['payment_date'] = $value;
        }
    }

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';

    const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING => 'Menunggu Persetujuan',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_PAID => 'Dibayar',
    ];

    const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_PENDING => 'warning',
        self::STATUS_APPROVED => 'success',
        self::STATUS_REJECTED => 'danger',
        self::STATUS_PAID => 'primary',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function vendorPayment()
    {
        return $this->belongsTo(VendorPayment::class);
    }

    /**
     * Get invoice records linked to this payment request.
     */
    public function invoices()
    {
        $ids = $this->selected_invoices ?? [];
        return Invoice::whereIn('id', $ids)->get();
    }

    /**
     * Generate next PR number.
     */
    public static function generateNumber(): string
    {
        $today = now()->format('Ymd');
        $prefix = "PR-{$today}-";
        $lastNumber = static::where('request_number', 'like', $prefix . '%')
            ->orderByDesc('request_number')
            ->value('request_number');

        $sequence = 1;
        if ($lastNumber) {
            $parts = explode('-', $lastNumber);
            $sequence = (int) end($parts) + 1;
        }

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
