<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\JournalEntry;

class VendorPayment extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'vendor_payments';
    protected $fillable = [
        'supplier_id',
        'selected_invoices',
        'invoice_receipts',
        'payment_date',
        'ntpn',
        'total_payment',
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
        'ppn_import_amount' => 'float',
        'pph22_amount' => 'float',
        'bea_masuk_amount' => 'float',
        'is_import_payment' => 'boolean',
    ];

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
