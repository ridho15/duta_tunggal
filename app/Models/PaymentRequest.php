<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Helpers\MoneyHelper;

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

    public function getStatusAttribute($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower(trim((string) $value))) {
            self::STATUS_DRAFT => self::STATUS_DRAFT,
            self::STATUS_PENDING => self::STATUS_PENDING,
            self::STATUS_APPROVED => self::STATUS_APPROVED,
            self::STATUS_PARTIAL => self::STATUS_PARTIAL,
            self::STATUS_REJECTED => self::STATUS_REJECTED,
            self::STATUS_PAID => self::STATUS_PAID,
            default => $value,
        };
    }

    public function setStatusAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['status'] = null;
            return;
        }

        $normalized = strtolower(trim((string) $value));

        $this->attributes['status'] = match ($normalized) {
            self::STATUS_DRAFT,
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_PARTIAL,
            self::STATUS_REJECTED,
            self::STATUS_PAID => $normalized,
            default => $value,
        };
    }

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
    const STATUS_PARTIAL = 'partial';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';

    const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING => 'Menunggu Persetujuan',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_PARTIAL => 'Dibayar Sebagian',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_PAID => 'Dibayar',
    ];

    const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_PENDING => 'warning',
        self::STATUS_APPROVED => 'success',
        self::STATUS_PARTIAL => 'info',
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
     * Generate next Payment Request number (format: PAY-REQ-YYYYMMDD-XXXX).
     */
    public static function generateNumber(): string
    {
        return \App\Services\SequentialNumberGenerator::generate('payment_requests', 'request_number', 'PAY-REQ-', 4, 'Ymd');
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) VendorPayment::where('payment_request_id', $this->id)->sum('total_payment');
    }

    public function getRemainingAmountAttribute(): float
    {
        $total = MoneyHelper::safeParse($this->total_amount ?? 0);
        return max(0, $total - $this->paid_amount);
    }

    /**
     * Calculate active PR amounts allocated for a given invoice (excluding given PR id).
     */
    public static function getActivePrAmountForInvoice(int $invoiceId, ?int $excludePrId = null): float
    {
        $activePrs = static::query()
            ->when($excludePrId, fn ($q) => $q->where('id', '!=', $excludePrId))
            ->whereIn('status', [
                self::STATUS_DRAFT,   // FIX #3: Draft PR sudah harus diperhitungkan agar tidak ada double claim
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
                self::STATUS_PARTIAL,
            ])
            ->get();

        $allocated = 0.0;
        foreach ($activePrs as $pr) {
            $selected = $pr->selected_invoices;
            if (is_string($selected)) {
                $selected = json_decode($selected, true);
            }
            if (! is_array($selected) || ! in_array($invoiceId, $selected)) {
                continue;
            }

            $prRemaining = (float) $pr->remaining_amount;
            if ($prRemaining <= 0) {
                continue;
            }

            if (count($selected) === 1) {
                $allocated += $prRemaining;
            } else {
                $invoices = Invoice::whereIn('id', $selected)->get();
                $sumTotals = (float) $invoices->sum('total');
                $thisInvoice = $invoices->firstWhere('id', $invoiceId);
                $thisTotal = (float) ($thisInvoice?->total ?? 0);
                $proportion = $sumTotals > 0 ? ($thisTotal / $sumTotals) : (1 / count($selected));
                $allocated += ($prRemaining * $proportion);
            }
        }

        return $allocated;
    }

    /**
     * Calculate remaining payable debt for an invoice, deducting other active PRs.
     */
    public static function getInvoiceRemainingPayable(Invoice $invoice, ?int $excludePrId = null): float
    {
        if ($invoice->isCancelled()) {
            return 0.0;
        }

        $invoice->load('accountPayable');
        $ap = $invoice->accountPayable;
        $remainingAp = ($ap && $ap->exists)
            ? (float) ($ap->remaining_original ?? $ap->remaining ?? $invoice->total)
            : (float) $invoice->total;

        $activePrAmount = static::getActivePrAmountForInvoice((int) $invoice->id, $excludePrId);

        return max(0.0, $remainingAp - $activePrAmount);
    }
}
