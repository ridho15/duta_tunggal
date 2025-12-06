<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReceipt extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'customer_receipts';
    protected $fillable = [
        'invoice_id',
        'customer_id',
        'selected_invoices',
        'invoice_receipts',
        'payment_date',
        'ntpn',
        'total_payment',
        'notes',
        'diskon',
        'payment_adjustment',
        'payment_method',
        'coa_id',
        'status', // 'Draft','Partial','Paid'
        'created_by',
        'cabang_id'
    ];

    protected $casts = [
        'selected_invoices' => 'json',
        'invoice_receipts' => 'json',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function customerReceiptItem()
    {
        return $this->hasMany(CustomerReceiptItem::class, 'customer_receipt_id');
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }
    
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }
    
    /**
     * Recalculate total_payment from CustomerReceiptItems
     */
    public function recalculateTotalPayment()
    {
        $total = $this->customerReceiptItem()->sum('amount');
        $this->update(['total_payment' => $total]);
        return $total;
    }
    
    /**
     * Get calculated total from CustomerReceiptItems
     */
    public function getCalculatedTotalAttribute()
    {
        return $this->customerReceiptItem()->sum('amount');
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
