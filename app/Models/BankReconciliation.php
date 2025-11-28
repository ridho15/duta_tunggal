<?php

namespace App\Models;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankReconciliation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'coa_id', 'period_start', 'period_end', 'statement_ending_balance', 'book_balance', 'difference', 'reference', 'notes', 'status'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'statement_ending_balance' => 'decimal:2',
        'book_balance' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'bank_recon_id');
    }

    public function unreconciledEntries()
    {
        // Use the defined relationship name on ChartOfAccount (journalEntries)
        return $this->coa->journalEntries()
            ->whereBetween('date', [$this->period_start, $this->period_end])
            ->whereNull('bank_recon_id')
            ->where(function ($query) {
                $query->where('debit', '>', 0)->orWhere('credit', '>', 0);
            });
    }
}
