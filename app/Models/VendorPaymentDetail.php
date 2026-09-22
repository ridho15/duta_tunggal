<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use App\Traits\CascadesJournalEntries;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorPaymentDetail extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity, CascadesJournalEntries;
    protected $table = 'vendor_payment_details';
    protected $fillable = [
        'vendor_payment_id',
        'invoice_id',
        'currency_id',
        'exchange_rate',
        'method',
        'amount',
        'amount_idr',
        'adjustment_amount',
        'balance_amount',
        'coa_id',
        'payment_date',
        'notes'
    ];

    protected $casts = [
        'currency_id' => 'integer',
        'exchange_rate' => 'decimal:8',
        'amount' => 'float',
        'amount_idr' => 'float',
        'adjustment_amount' => 'float',
        'balance_amount' => 'float',
    ];

    public function vendorPayment()
    {
        return $this->belongsTo(VendorPayment::class, 'vendor_payment_id')->withDefault();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function depositLog()
    {
        return $this->morphMany(DepositLog::class, 'reference');
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }
}
