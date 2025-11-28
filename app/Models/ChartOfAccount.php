<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'chart_of_accounts';
    protected $fillable = [
        'code',
        'name',
        'type', //'Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Contra Asset',
        'parent_id',
        'is_active',
        'is_current',
        'description',
        'opening_balance',
        'debit',
        'credit',
        'ending_balance'
    ];

    public function coaParent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id')->withDefault();
    }

    public function children()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'coa_id');
    }

    /**
     * Get the normal balance type for this account type
     * 
     * @return string 'debit' or 'credit'
     */
    public function getNormalBalanceAttribute()
    {
        return match ($this->type) {
            'Asset', 'Expense' => 'debit',
            'Liability', 'Equity', 'Revenue', 'Contra Asset' => 'credit',
            default => 'debit',
        };
    }

    /**
     * Calculate ending balance based on account type formula
     * 
     * @return float
     */
    public function calculateEndingBalance()
    {
        // Get all journal entries for this account
        $entries = $this->journalEntries;

        $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');

        // Calculate balance based on account type (normal balance)
        // Asset: Debit increases, Credit decreases
        // Contra Asset: Credit increases, Debit decreases (contra to asset)
        // Liability & Equity: Credit increases, Debit decreases
        $balance = match ($this->type) {
            'Asset', 'Expense' => $this->opening_balance + $totalDebit - $totalCredit,
            'Liability', 'Equity', 'Revenue', 'Contra Asset' => $this->opening_balance - $totalDebit + $totalCredit,
            default => $this->opening_balance + $totalDebit - $totalCredit,
        };

        return $balance;
    }

    /**
     * Update ending balance automatically
     */
    public function updateEndingBalance()
    {
        $this->ending_balance = $this->calculateEndingBalance();
        $this->save();
    }

    /**
     * Get balance calculation formula description
     * 
     * @return string
     */
    public function getBalanceFormulaAttribute()
    {
        return match ($this->type) {
            'Asset', 'Expense' => 'Saldo Awal + Debit - Kredit',
            'Liability', 'Equity', 'Revenue', 'Contra Asset' => 'Saldo Awal - Debit + Kredit',
            default => 'Saldo Awal + Debit - Kredit',
        };
    }

    /**
     * Get formatted name with code
     * 
     * @return string
     */
    public function getFormattedNameAttribute()
    {
        return "{$this->code} - {$this->name}";
    }
}
