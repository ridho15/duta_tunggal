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
        return $this->belongsTo(ChartOfAccount::class);
    }
}
