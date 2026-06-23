<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use App\Traits\CascadesJournalEntries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\JournalEntry;

class VendorPayment extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity, CascadesJournalEntries;
    protected $table = 'vendor_payments';

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PARTIAL = 'Partial';
    public const STATUS_PAID = 'Paid';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PARTIAL => 'Partial',
        self::STATUS_PAID => 'Paid',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_PARTIAL => 'warning',
        self::STATUS_PAID => 'success',
    ];

    protected $fillable = [
        'payment_request_id', // Task 15c: link to PaymentRequest
        'supplier_id',
        'selected_invoices',
        'invoice_receipts',
        'currency_id',
        'exchange_rate',
        'payment_date',
        'ntpn',
        'total_payment',
        'total_payment_idr',
        'coa_id',
        'payment_method',
        'notes',
        'diskon',
        'payment_adjustment',
        'status', //'Draft', 'Partial', 'Paid'
        'is_import_payment',
        'ppn_import_amount',
        'pph22_amount',
        'bea_masuk_amount',
    ];

    protected $casts = [
        'selected_invoices' => 'array',
        'invoice_receipts' => 'array',
        'currency_id' => 'integer',
        'exchange_rate' => 'decimal:8',
        'total_payment_idr' => 'float',
        'ppn_import_amount' => 'float',
        'pph22_amount' => 'float',
        'bea_masuk_amount' => 'float',
        'is_import_payment' => 'boolean',
        // payment_date handled via accessor/mutator to guard against invalid DB values like '-'
    ];

    /**
     * Accessor for payment_date — guards against invalid DB values like '-'.
     */
    public function getPaymentDateAttribute($value): ?\Illuminate\Support\Carbon
    {
        if (!$value || trim((string) $value) === '' || trim((string) $value) === '-') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable $e) {
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
            $this->attributes['payment_date'] = $value instanceof \Illuminate\Support\Carbon ? $value->toDateString() : $value;
        }
    }

    public function getStatusAttribute($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower(trim((string) $value))) {
            'draft' => self::STATUS_DRAFT,
            'partial' => self::STATUS_PARTIAL,
            'paid' => self::STATUS_PAID,
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
            'draft' => self::STATUS_DRAFT,
            'partial' => self::STATUS_PARTIAL,
            'paid' => self::STATUS_PAID,
            default => $value,
        };
    }

    public function paymentRequest()
    {
        return $this->belongsTo(\App\Models\PaymentRequest::class, 'payment_request_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id')->withDefault();
    }

    public function vendorPaymentDetail()
    {
        return $this->hasMany(VendorPaymentDetail::class, 'vendor_payment_id');
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class, 'from_model_id')
            ->where('from_model_type', Supplier::class)
            ->where('from_model_id', $this->supplier_id);
    }

    /**
     * Recalculate total_payment from VendorPaymentDetail
     */
    public function recalculateTotalPayment()
    {
        $total = $this->vendorPaymentDetail()->sum('amount');
        $this->update(['total_payment' => $total]);
        return $total;
    }

    /**
     * Get calculated total from VendorPaymentDetail
     */
    public function getCalculatedTotalAttribute()
    {
        return $this->vendorPaymentDetail()->sum('amount');
    }

    /**
     * Get reference for journal entries
     */
    public function getReferenceAttribute()
    {
        return $this->ntpn ?: 'VP-' . $this->id;
    }
}
