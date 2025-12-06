<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtherSale extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $table = 'other_sales';

    protected $casts = [
        'transaction_date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected $fillable = [
        'reference_number',
        'transaction_date',
        'type',
        'description',
        'amount',
        'coa_id',
        'cash_bank_account_id',
        'customer_id',
        'cabang_id',
        'status',
        'notes',
        'created_by',
    ];

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    public function cashBankAccount()
    {
        return $this->belongsTo(CashBankAccount::class, 'cash_bank_account_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    // Helper method to check if journal entries have been posted
    public function hasPostedJournals()
    {
        return $this->journalEntries()->exists();
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }
}
