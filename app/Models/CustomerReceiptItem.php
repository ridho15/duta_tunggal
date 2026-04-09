<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use App\Traits\CascadesJournalEntries;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReceiptItem extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity, CascadesJournalEntries;
    protected $table = 'customer_receipt_items';
    protected $fillable = [
        'customer_receipt_id',
        'invoice_id',
        'method',
        'amount',
        'coa_id',
        'payment_date',
        'selected_invoices',
    ];

    protected $casts = [
        'selected_invoices' => 'array',
    ];

    public function customerReceipt()
    {
        return $this->belongsTo(CustomerReceipt::class, 'customer_receipt_id')->withDefault();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function depositLog()
    {
        return $this->morphMany(DepositLog::class, 'reference');
    }
}
