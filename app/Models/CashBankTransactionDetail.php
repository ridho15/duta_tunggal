<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBankTransactionDetail extends Model
{
    protected $fillable = [
        'cash_bank_transaction_id',
        'chart_of_account_id',
        'amount',
        'description',
        'ntpn',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function cashBankTransaction(): BelongsTo
    {
        return $this->belongsTo(CashBankTransaction::class);
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    protected static function booted()
    {
        // Regenerate parent transaction's journal entries when detail is updated or deleted
        static::saved(function ($detail) {
            if ($detail->cashBankTransaction && $detail->wasChanged(['chart_of_account_id', 'amount', 'description'])) {
                $transaction = $detail->cashBankTransaction;
                $hasExistingEntries = $transaction->journalEntries()->exists();

                if ($hasExistingEntries) {
                    app(\App\Services\CashBankService::class)->postTransaction($transaction);
                }
            }
        });

        static::deleted(function ($detail) {
            if ($detail->cashBankTransaction) {
                $transaction = $detail->cashBankTransaction;
                $hasExistingEntries = $transaction->journalEntries()->exists();

                if ($hasExistingEntries) {
                    app(\App\Services\CashBankService::class)->postTransaction($transaction);
                }
            }
        });
    }
}
