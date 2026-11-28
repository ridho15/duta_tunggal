<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseRequestLine extends Model
{
    protected $fillable = ['expense_request_id', 'coa_id', 'description', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function coa(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }
}
