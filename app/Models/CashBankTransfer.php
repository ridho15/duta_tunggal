<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use App\Traits\CascadesJournalEntries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBankTransfer extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity, CascadesJournalEntries;

    protected $fillable = [
        'number', 
        'date', 
        'from_coa_id', 
        'to_coa_id', 
        'amount', 
        'other_costs', 
        'other_costs_coa_id',
        'description', 
        'reference', 
        'attachment_path', 
        'status',
        'cancel_reason',
        'cancelled_by',
        'cancelled_at',
        'cabang_id'
    ];

    protected $casts = [
        'date' => 'date',
        'cancelled_at' => 'datetime',
        'amount' => 'decimal:2',
        'other_costs' => 'decimal:2',
    ];

    public function fromCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'from_coa_id');
    }

    public function toCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'to_coa_id');
    }

    public function otherCostsCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'other_costs_coa_id');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return strtolower((string) $this->status) === 'cancelled';
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
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
